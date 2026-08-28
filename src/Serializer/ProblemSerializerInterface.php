<?php

declare(strict_types=1);

namespace Linkedcode\Middleware\Problem\Serializer;

interface ProblemSerializerInterface
{
    /** @param array<string, mixed> $data */
    public function serialize(array $data): string;

    public function contentType(): string;
}
