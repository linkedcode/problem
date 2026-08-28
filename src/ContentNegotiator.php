<?php

declare(strict_types=1);

namespace Linkedcode\Middleware\Problem;

use Psr\Http\Message\ServerRequestInterface;
use Linkedcode\Middleware\Problem\Serializer\ProblemSerializerFactory;
use Linkedcode\Middleware\Problem\Serializer\ProblemSerializerInterface;

/**
 * Picks a problem serializer from the request's Accept header, honouring
 * quality values so that `application/json;q=0.1, application/xml;q=0.9`
 * resolves to XML and `application/xml;q=0` never does.
 */
final class ContentNegotiator
{
    /** Media type => format, in preference order for equal q-values. */
    private const MEDIA_TYPES = [
        'application/problem+json' => 'json',
        'application/json'         => 'json',
        'application/problem+xml'  => 'xml',
        'application/xml'          => 'xml',
        'text/xml'                 => 'xml',
    ];

    private const DEFAULT_FORMAT = 'json';

    public function __construct(
        private readonly ProblemSerializerFactory $factory = new ProblemSerializerFactory(),
    ) {}

    public function negotiate(ServerRequestInterface $request): ProblemSerializerInterface
    {
        $accept = $request->getHeaderLine('Accept');

        if (trim($accept) === '') {
            return $this->factory->create(self::DEFAULT_FORMAT);
        }

        $ranges = $this->parseAccept($accept);

        if ($ranges === []) {
            return $this->factory->create(self::DEFAULT_FORMAT);
        }

        $bestFormat = null;
        $bestQuality = 0.0;

        foreach (self::MEDIA_TYPES as $mediaType => $format) {
            $quality = $this->qualityFor($mediaType, $ranges);

            if ($quality > $bestQuality) {
                $bestQuality = $quality;
                $bestFormat = $format;
            }
        }

        // Everything acceptable was explicitly refused (q=0): fall back rather
        // than fail, since this is already an error response.
        return $this->factory->create($bestFormat ?? self::DEFAULT_FORMAT);
    }

    /**
     * Best quality the client assigned to $mediaType, considering exact matches,
     * type wildcards (application/*) and the catch-all (*\/*). More specific
     * ranges win over wildcards regardless of order.
     *
     * @param array<string, float> $ranges
     */
    private function qualityFor(string $mediaType, array $ranges): float
    {
        if (isset($ranges[$mediaType])) {
            return $ranges[$mediaType];
        }

        [$type] = explode('/', $mediaType, 2);

        if (isset($ranges[$type . '/*'])) {
            return $ranges[$type . '/*'];
        }

        return $ranges['*/*'] ?? 0.0;
    }

    /**
     * @return array<string, float> media range => quality
     */
    private function parseAccept(string $accept): array
    {
        $ranges = [];

        foreach (explode(',', $accept) as $part) {
            $segments = explode(';', trim($part));
            $mediaRange = strtolower(trim(array_shift($segments) ?? ''));

            if ($mediaRange === '') {
                continue;
            }

            $quality = 1.0;

            foreach ($segments as $segment) {
                $segment = trim($segment);

                if (stripos($segment, 'q=') === 0) {
                    $value = substr($segment, 2);
                    $quality = is_numeric($value) ? max(0.0, min(1.0, (float) $value)) : 1.0;
                    break;
                }
            }

            $ranges[$mediaRange] = $quality;
        }

        return $ranges;
    }
}
