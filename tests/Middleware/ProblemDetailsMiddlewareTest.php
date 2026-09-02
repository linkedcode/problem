<?php

declare(strict_types=1);

namespace Linkedcode\Middleware\Problem\Tests\Middleware;

use Linkedcode\Middleware\Problem\Mapper\ExceptionMapperInterface;
use Linkedcode\Middleware\Problem\Middleware\ProblemDetailsMiddleware;
use Linkedcode\Middleware\Problem\Problem;
use Linkedcode\Middleware\Problem\ProblemInterface;
use Linkedcode\Middleware\Problem\ProblemResponseFactory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\AbstractLogger;
use RuntimeException;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;
use Throwable;

final class ProblemDetailsMiddlewareTest extends TestCase
{
    /** A message shaped like what a real PDOException carries. */
    private const LEAKY_MESSAGE =
        'SQLSTATE[42S02]: Base table or view not found: 1146 Table "prod_db.orders" does not exist';

    public function testServerErrorDetailIsScrubbed(): void
    {
        $middleware = $this->middleware($this->mapperReturning(
            new Problem('about:blank', 'Internal Server Error', 500, self::LEAKY_MESSAGE)
        ));

        $body = $this->decode($middleware->process($this->request(), $this->throwingHandler()));

        $this->assertSame(500, $body['status']);
        $this->assertSame('Internal Server Error', $body['title']);
        $this->assertStringNotContainsString('prod_db', $body['detail']);
        $this->assertStringNotContainsString('SQLSTATE', $body['detail']);
    }

    public function testServerErrorExtensionsAreDropped(): void
    {
        $middleware = $this->middleware($this->mapperReturning(
            new Problem('about:blank', 'Internal Server Error', 500, 'boom', null, ['query' => 'SELECT * FROM users'])
        ));

        $body = $this->decode($middleware->process($this->request(), $this->throwingHandler()));

        $this->assertArrayNotHasKey('query', $body);
    }

    public function testClientErrorDetailIsPreserved(): void
    {
        // A 4xx detail is a deliberate message for the caller, not a leak.
        $middleware = $this->middleware($this->mapperReturning(
            new Problem('about:blank', 'Unprocessable Entity', 422, 'email is not valid')
        ));

        $body = $this->decode($middleware->process($this->request(), $this->throwingHandler()));

        $this->assertSame('email is not valid', $body['detail']);
    }

    public function testClientErrorExtensionsArePreserved(): void
    {
        $middleware = $this->middleware($this->mapperReturning(
            new Problem('about:blank', 'Unprocessable Entity', 422, 'invalid', null, ['errors' => ['email']])
        ));

        $body = $this->decode($middleware->process($this->request(), $this->throwingHandler()));

        $this->assertSame(['email'], $body['errors']);
    }

    public function testDebugModeExposesTheRealDetail(): void
    {
        $middleware = $this->middleware(
            $this->mapperReturning(new Problem('about:blank', 'Internal Server Error', 500, self::LEAKY_MESSAGE)),
            debug: true
        );

        $body = $this->decode($middleware->process($this->request(), $this->throwingHandler()));

        $this->assertSame(self::LEAKY_MESSAGE, $body['detail']);
    }

    public function testTheFullExceptionStillReachesTheLogger(): void
    {
        $logger = new CollectingLogger();
        $middleware = $this->middleware(
            $this->mapperReturning(new Problem('about:blank', 'Internal Server Error', 500, self::LEAKY_MESSAGE)),
            logger: $logger
        );

        $middleware->process($this->request(), $this->throwingHandler());

        // Scrubbing the response must not blind the operator.
        $this->assertNotEmpty($logger->records);
        $this->assertStringContainsString('kaboom', $logger->records[0]['message']);
        $this->assertInstanceOf(Throwable::class, $logger->records[0]['context']['exception']);
    }

    public function testTheLoggerGetsTheRequestThatFailed(): void
    {
        $logger = new CollectingLogger();
        $middleware = $this->middleware(
            $this->mapperReturning(new Problem('about:blank', 'Not Found', 404, 'Not found.')),
            logger: $logger
        );

        $request = (new ServerRequestFactory())
            ->createServerRequest('POST', 'https://example.test/orders/42?page=2', ['REMOTE_ADDR' => '203.0.113.7'])
            ->withHeader('User-Agent', 'curl/8.5.0')
            ->withHeader('Referer', 'https://example.test/cart')
            ->withHeader('X-Request-Id', 'req-abc');

        $middleware->process($request, $this->throwingHandler());

        $context = $logger->records[0]['context'];

        $this->assertSame('POST', $context['method']);
        $this->assertSame('/orders/42', $context['path']);
        $this->assertSame('curl/8.5.0', $context['user_agent']);
        $this->assertSame('https://example.test/cart', $context['referer']);
        $this->assertSame('req-abc', $context['request_id']);
        $this->assertSame('203.0.113.7', $context['ip']);
    }

    public function testAbsentRequestDetailsAreOmittedRatherThanLoggedEmpty(): void
    {
        $logger = new CollectingLogger();
        $middleware = $this->middleware(
            $this->mapperReturning(new Problem('about:blank', 'Not Found', 404, 'Not found.')),
            logger: $logger
        );

        $middleware->process($this->request(), $this->throwingHandler());

        $context = $logger->records[0]['context'];

        $this->assertSame('/', $context['path']);
        $this->assertArrayNotHasKey('query', $context);
        $this->assertArrayNotHasKey('route', $context);
        $this->assertArrayNotHasKey('referer', $context);
        $this->assertArrayNotHasKey('ip', $context);
    }

    public function testTheMatchedRoutePatternIsLoggedWhenRoutingResolved(): void
    {
        $logger = new CollectingLogger();
        $middleware = $this->middleware(
            $this->mapperReturning(new Problem('about:blank', 'Internal Server Error', 500, 'boom')),
            logger: $logger
        );

        // Read structurally, so anything exposing getPattern() works.
        $route = new class {
            public function getPattern(): string
            {
                return '/orders/{id}';
            }
        };

        $request = $this->request()->withAttribute('__route__', $route);

        $middleware->process($request, $this->throwingHandler());

        $this->assertSame('/orders/{id}', $logger->records[0]['context']['route']);
    }

    public function testTheQueryStringNeverReachesTheLog(): void
    {
        $logger = new CollectingLogger();
        $middleware = $this->middleware(
            $this->mapperReturning(new Problem('about:blank', 'Not Found', 404, 'Not found.')),
            logger: $logger
        );

        $request = (new ServerRequestFactory())->createServerRequest(
            'GET',
            'https://example.test/callback?code=super-secret&page=2'
        );

        $middleware->process($request, $this->throwingHandler());

        $context = $logger->records[0]['context'];

        // The path still identifies what was requested; the parameters do not
        // get logged at all, sensitive or otherwise.
        $this->assertSame('/callback', $context['path']);
        $this->assertArrayNotHasKey('query', $context);
        $this->assertStringNotContainsString(
            'super-secret',
            json_encode(array_diff_key($context, ['exception' => null]))
        );
    }

    public function testAuthorizationAndCookieHeadersNeverReachTheLog(): void
    {
        $logger = new CollectingLogger();
        $middleware = $this->middleware(
            $this->mapperReturning(new Problem('about:blank', 'Not Found', 404, 'Not found.')),
            logger: $logger
        );

        $request = $this->request()
            ->withHeader('Authorization', 'Bearer super-secret-jwt')
            ->withHeader('Cookie', 'session=super-secret-session');

        $middleware->process($request, $this->throwingHandler());

        $encoded = json_encode(array_diff_key($logger->records[0]['context'], ['exception' => null]));

        $this->assertStringNotContainsString('super-secret-jwt', $encoded);
        $this->assertStringNotContainsString('super-secret-session', $encoded);
    }

    public function testSuccessfulRequestsPassThroughUntouched(): void
    {
        $middleware = $this->middleware($this->mapperReturning(
            new Problem('about:blank', 'Internal Server Error', 500, 'unused')
        ));

        $factory = new ResponseFactory();
        $handler = new class ($factory) implements RequestHandlerInterface {
            public function __construct(private readonly ResponseFactory $factory) {}

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return $this->factory->createResponse(204);
            }
        };

        $this->assertSame(204, $middleware->process($this->request(), $handler)->getStatusCode());
    }

    // -- helpers ---------------------------------------------------------

    private function middleware(
        ExceptionMapperInterface $mapper,
        bool $debug = false,
        ?CollectingLogger $logger = null
    ): ProblemDetailsMiddleware {
        return new ProblemDetailsMiddleware(
            $mapper,
            new ProblemResponseFactory(new ResponseFactory()),
            $logger,
            $debug
        );
    }

    private function mapperReturning(ProblemInterface $problem): ExceptionMapperInterface
    {
        return new class ($problem) implements ExceptionMapperInterface {
            public function __construct(private readonly ProblemInterface $problem) {}

            public function map(Throwable $e): ProblemInterface
            {
                return $this->problem;
            }
        };
    }

    private function throwingHandler(): RequestHandlerInterface
    {
        return new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                throw new RuntimeException('kaboom');
            }
        };
    }

    private function request(): ServerRequestInterface
    {
        return (new ServerRequestFactory())->createServerRequest('GET', 'https://example.test/');
    }

    /** @return array<string, mixed> */
    private function decode(ResponseInterface $response): array
    {
        return json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
    }
}

final class CollectingLogger extends AbstractLogger
{
    /** @var list<array{message: string, context: array<string, mixed>}> */
    public array $records = [];

    public function log($level, \Stringable|string $message, array $context = []): void
    {
        $this->records[] = ['message' => (string) $message, 'context' => $context];
    }
}
