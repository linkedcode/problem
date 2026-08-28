<?php

declare(strict_types=1);

namespace Linkedcode\Middleware\Problem\Serializer;

final class JsonProblemSerializer implements ProblemSerializerInterface
{
    /** @param array<string, mixed> $data */
    public function serialize(array $data): string
    {
        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
    }

    public function contentType(): string
    {
        return 'application/problem+json';
    }
}
