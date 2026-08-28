<?php

declare(strict_types=1);

namespace Linkedcode\Middleware\Problem;

interface ProblemInterface
{
    public function getType(): string;
    public function getTitle(): string;
    public function getStatus(): int;
    public function getDetail(): ?string;
    public function getInstance(): ?string;
    /** @return array<string, mixed> */
    public function getExtensions(): array;
}
