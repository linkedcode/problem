<?php

declare(strict_types=1);

namespace Linkedcode\Middleware\Problem\Exception;

class HttpException extends ProblemException
{
    /** @param array<string, mixed> $extensions */
    public function __construct(
        int $status,
        string $message = '',
        array $extensions = []
    ) {
        parent::__construct($message);
        $this->status = $status;
        $this->title = $message ?: 'HTTP Error';
        $this->extensions = $extensions;
    }
}
