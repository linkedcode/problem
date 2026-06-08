<?php

declare(strict_types=1);

namespace Linkedcode\Middleware\Problem\Exception;

use Linkedcode\Middleware\Problem\ValidationError;

final class ValidationException extends ProblemException
{
    protected int $status = 422;
    protected string $title = 'Unprocessable Entity';

    /** @param ValidationError[] $errors */
    public function __construct(private readonly array $errors)
    {
        parent::__construct('Validation failed');
        $this->extensions = [
            'errors' => array_map(fn(ValidationError $e) => $e->toArray(), $errors),
        ];
    }

    /** @return ValidationError[] */
    public function errors(): array
    {
        return $this->errors;
    }
}
