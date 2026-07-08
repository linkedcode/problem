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
use Linkedcode\Middleware\Problem\ProblemResponseFactory;

final class ProblemDetailsMiddleware implements MiddlewareInterface
{
    private readonly LoggerInterface $logger;

    public function __construct(
        private readonly ExceptionMapperInterface $mapper,
        private readonly ProblemResponseFactory $responseFactory,
        ?LoggerInterface $logger = null
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

            return $this->responseFactory->create($problem, $request);
        }
    }
}
