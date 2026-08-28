<?php

declare(strict_types=1);

namespace Linkedcode\Middleware\Problem\Exception;

use Linkedcode\Middleware\Problem\Problem;
use Linkedcode\Middleware\Problem\ProblemInterface;
use RuntimeException;

abstract class ProblemException extends RuntimeException
{
    protected int $status = 500;
    protected string $type = 'about:blank';
    protected string $title = 'Internal Server Error';
    /** @var array<string, mixed> */
    protected array $extensions = [];

    public function toProblem(): ProblemInterface
    {
        return new Problem(
            type: $this->type,
            title: $this->title,
            status: $this->status,
            detail: $this->getMessage(),
            extensions: $this->extensions
        );
    }
}
