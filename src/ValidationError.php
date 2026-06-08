<?php

declare(strict_types=1);

namespace Linkedcode\Middleware\Problem;

final class ValidationError
{
    public function __construct(
        private readonly string $field,
        private readonly string $message,
    ) {}

    public function field(): string   { return $this->field; }
    public function message(): string { return $this->message; }

    public function toArray(): array
    {
        return [
            'field'   => $this->field,
            'message' => $this->message,
        ];
    }
}
