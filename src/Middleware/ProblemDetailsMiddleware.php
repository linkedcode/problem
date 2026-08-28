<?php

declare(strict_types=1);

namespace Linkedcode\Middleware\Problem\Middleware;

use Throwable;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Psr\Log\NullLogger;
use Linkedcode\Middleware\Problem\Mapper\ExceptionMapperInterface;
use Linkedcode\Middleware\Problem\Problem;
use Linkedcode\Middleware\Problem\ProblemInterface;
use Linkedcode\Middleware\Problem\ProblemResponseFactory;

/**
 * Turns any uncaught Throwable into an RFC 9457 problem response.
 *
 * Server errors (5xx) are scrubbed before they leave the process: the mapper's
 * detail and extensions are dropped and replaced with a generic message. A
 * mapper that forwards $e->getMessage() into the detail — the natural way to
 * write one — otherwise leaks database names, table names, file paths and SQL
 * to the client on every unexpected failure. The full exception always reaches
 * the logger regardless.
 *
 * Set $debug to true in local development to see the real detail in the
 * response body. Never enable it in production.
 */
final class ProblemDetailsMiddleware implements MiddlewareInterface
{
    private const GENERIC_SERVER_ERROR_DETAIL = 'An unexpected error occurred.';

    private readonly LoggerInterface $logger;

    /**
     * @param bool $debug Expose the mapper's real 5xx detail in the response.
     *                    Development only.
     */
    public function __construct(
        private readonly ExceptionMapperInterface $mapper,
        private readonly ProblemResponseFactory $responseFactory,
        ?LoggerInterface $logger = null,
        private readonly bool $debug = false
    ) {
        $this->logger = $logger ?? new NullLogger();
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            return $handler->handle($request);
        } catch (Throwable $e) {
            $problem = $this->mapper->map($e);

            $this->logger->log(
                $problem->getStatus() >= 500 ? LogLevel::ERROR : LogLevel::WARNING,
                $e->getMessage(),
                ['exception' => $e, 'status' => $problem->getStatus()]
            );

            return $this->responseFactory->create($this->scrub($problem), $request);
        }
    }

    /**
     * Strip internals from server errors. 4xx are left untouched: their detail
     * is a deliberate message for the caller ("email is not valid"), not an
     * accident of the underlying failure.
     */
    private function scrub(ProblemInterface $problem): ProblemInterface
    {
        if ($this->debug || $problem->getStatus() < 500) {
            return $problem;
        }

        return new Problem(
            type: $problem->getType(),
            title: $problem->getTitle(),
            status: $problem->getStatus(),
            detail: self::GENERIC_SERVER_ERROR_DETAIL,
            instance: $problem->getInstance(),
        );
    }
}
