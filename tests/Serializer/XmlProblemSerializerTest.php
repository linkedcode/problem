<?php

declare(strict_types=1);

namespace Linkedcode\Middleware\Problem\Tests\Serializer;

use PHPUnit\Framework\TestCase;
use Linkedcode\Middleware\Problem\Serializer\XmlProblemSerializer;

final class XmlProblemSerializerTest extends TestCase
{
    private XmlProblemSerializer $serializer;

    protected function setUp(): void
    {
        $this->serializer = new XmlProblemSerializer();
    }

    public function testContentType(): void
    {
        $this->assertSame('application/problem+xml', $this->serializer->contentType());
    }

    public function testSerializesScalarFields(): void
    {
        $data = ['type' => 'about:blank', 'title' => 'Not Found', 'status' => 404];
        $result = $this->serializer->serialize($data);

        $xml = new \SimpleXMLElement($result);
        $this->assertSame('about:blank', (string) $xml->type);
        $this->assertSame('Not Found', (string) $xml->title);
        $this->assertSame('404', (string) $xml->status);
    }

    public function testNamespace(): void
    {
        $result = $this->serializer->serialize(['status' => 404]);
        $this->assertStringContainsString('urn:ietf:rfc:7807', $result);
    }

    public function testSerializesNestedArray(): void
    {
        $data = ['errors' => [['field' => 'email', 'message' => 'Invalid']]];
        $result = $this->serializer->serialize($data);

        $xml = new \SimpleXMLElement($result);
        $this->assertSame('email', (string) $xml->errors->item->field);
        $this->assertSame('Invalid', (string) $xml->errors->item->message);
    }

    public function testSpecialCharactersAreEscapedExactlyOnce(): void
    {
        // Regression: htmlspecialchars() before addChild() double-encoded, so
        // 'Tom & Jerry' came back out as 'Tom &amp; Jerry'.
        $result = $this->serializer->serialize(['detail' => 'Tom & Jerry <3']);

        $xml = new \SimpleXMLElement($result);
        $this->assertSame('Tom & Jerry <3', (string) $xml->detail);
    }

    public function testInvalidElementNamesAreSanitised(): void
    {
        // '123' is normalised to an int key by PHP itself and becomes <item>;
        // 'foo bar' would otherwise emit malformed XML.
        $result = $this->serializer->serialize(['foo bar' => 'x', '123' => 'y']);

        $xml = new \SimpleXMLElement($result);
        $this->assertSame('x', (string) $xml->foo_bar);
        $this->assertSame('y', (string) $xml->item);
    }

    public function testNamesStartingWithADigitAreMadeValid(): void
    {
        // A key that stays a string but is not a legal XML name.
        $result = $this->serializer->serialize(['1st-error' => 'boom']);

        $xml = new \SimpleXMLElement($result);
        $this->assertSame('boom', (string) $xml->{'_1st-error'});
    }

    public function testBooleansAndNullsAreSerialisedReadably(): void
    {
        $result = $this->serializer->serialize(['ok' => false, 'missing' => null]);

        $xml = new \SimpleXMLElement($result);
        $this->assertSame('false', (string) $xml->ok);
        $this->assertSame('', (string) $xml->missing);
    }
}
