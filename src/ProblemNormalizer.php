<?php

declare(strict_types=1);

namespace Linkedcode\Middleware\Problem;

final class ProblemNormalizer
{
    /** @return array<string, mixed> */
    public function normalize(ProblemInterface $problem): array
    {
        $data = array_filter([
            'type' => $problem->getType(),
            'title' => $problem->getTitle(),
            'status' => $problem->getStatus(),
            'detail' => $problem->getDetail(),
            'instance' => $problem->getInstance(),
        ], fn ($v) => $v !== null);

        return array_merge($data, $problem->getExtensions());
    }
}
