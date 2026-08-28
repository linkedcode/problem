<?php

declare(strict_types=1);

namespace Linkedcode\Middleware\Problem;

final class Problem implements ProblemInterface
{
    public function __construct(
        private readonly string $type,
        private readonly string $title,
        private readonly int $status,
        private readonly ?string $detail = null,
        private readonly ?string $instance = null,
        /** @var array<string, mixed> */
        private readonly array $extensions = []
    ) {}

    public function getType(): string { return $this->type; }
    public function getTitle(): string { return $this->title; }
    public function getStatus(): int { return $this->status; }
    public function getDetail(): ?string { return $this->detail; }
    public function getInstance(): ?string { return $this->instance; }
    /** @return array<string, mixed> */
    public function getExtensions(): array { return $this->extensions; }
}
