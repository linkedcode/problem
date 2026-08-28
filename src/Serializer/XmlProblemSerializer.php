<?php

declare(strict_types=1);

namespace Linkedcode\Middleware\Problem\Serializer;

use SimpleXMLElement;

final class XmlProblemSerializer implements ProblemSerializerInterface
{
    private const ROOT = '<problem xmlns="urn:ietf:rfc:7807"/>';

    /** Element names must be valid XML NCNames; extensions are user-supplied. */
    private const VALID_NAME = '/^[A-Za-z_][A-Za-z0-9_.\-]*$/';

    /** @param array<string, mixed> $data */
    public function serialize(array $data): string
    {
        $xml = new SimpleXMLElement(self::ROOT);

        $this->appendChildren($xml, $data);

        return $xml->asXML() ?: self::ROOT;
    }

    public function contentType(): string
    {
        return 'application/problem+xml';
    }

    /**
     * @param array<array-key, mixed> $items
     */
    private function appendChildren(SimpleXMLElement $parent, array $items): void
    {
        foreach ($items as $key => $value) {
            $name = $this->elementName($key);

            if (is_array($value)) {
                $this->appendChildren($parent->addChild($name), $value);
                continue;
            }

            // addChild() escapes '<' but silently DROPS a bare '&', corrupting
            // the value. Assigning through the node encodes everything properly
            // and exactly once.
            $child = $parent->addChild($name);
            $child[0] = $this->stringify($value);
        }
    }

    /**
     * Numeric keys become <item>; names that are not valid XML element names are
     * sanitised rather than allowed to produce malformed output.
     */
    private function elementName(int|string $key): string
    {
        if (is_int($key)) {
            return 'item';
        }

        if (preg_match(self::VALID_NAME, $key) === 1) {
            return $key;
        }

        $sanitised = preg_replace('/[^A-Za-z0-9_.\-]/', '_', $key) ?? '';

        if ($sanitised === '' || preg_match('/^[A-Za-z_]/', $sanitised) !== 1) {
            $sanitised = '_' . $sanitised;
        }

        return $sanitised;
    }

    private function stringify(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if ($value === null) {
            return '';
        }

        if (is_scalar($value) || $value instanceof \Stringable) {
            return (string) $value;
        }

        return '';
    }
}
