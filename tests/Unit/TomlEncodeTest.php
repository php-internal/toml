<?php

declare(strict_types=1);

namespace Internal\Toml\Tests\Unit;

use Internal\Toml\Node\Document;
use Internal\Toml\Toml;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Toml::class)]
final class TomlEncodeTest extends TestCase
{
    public static function provideSimpleValues(): \Generator
    {
        yield 'string' => ['hello', '/test = [\'"]hello[\'"]/'];
        yield 'integer' => [42, '/test = 42/'];
        yield 'float' => [3.14, '/test = 3\.14/'];
        yield 'boolean true' => [true, '/test = true/'];
        yield 'boolean false' => [false, '/test = false/'];
    }

    public static function provideRoundTripData(): \Generator
    {
        yield 'simple key-value' => [
            ['title' => 'Example', 'version' => '1.0.0'],
        ];

        yield 'nested table' => [
            ['database' => ['host' => 'localhost', 'port' => 5432]],
        ];

        yield 'array values' => [
            ['ports' => [8000, 8001, 8002]],
        ];

        yield 'mixed types' => [
            ['str' => 'text', 'int' => 42, 'float' => 3.14, 'bool' => true],
        ];
    }

    public static function provideFixtureFiles(): \Generator
    {
        yield 'roadrunner config' => ['rr.toml'];
    }
    // ============================================
    // Basic Encoding Tests
    // ============================================

    public function testEncodeReturnsStringableDocument(): void
    {
        // Arrange
        $data = ['key' => 'value'];

        // Act
        $result = Toml::encode($data);

        // Assert
        self::assertInstanceOf(\Stringable::class, $result);
        self::assertInstanceOf(Document::class, $result);
    }

    public function testEncodeSimpleKeyValueProducesToml(): void
    {
        // Arrange
        $data = ['title' => 'TOML Example'];

        // Act
        $toml = (string) Toml::encode($data);

        // Assert
        self::assertStringContainsString('title = ', $toml);
        self::assertStringContainsString('TOML Example', $toml);
    }

    #[DataProvider('provideSimpleValues')]
    public function testEncodeVariousTypesProducesValidToml(mixed $value, string $expectedPattern): void
    {
        // Arrange
        $data = ['test' => $value];

        // Act
        $toml = (string) Toml::encode($data);

        // Assert
        self::assertMatchesRegularExpression($expectedPattern, $toml);
    }

    // ============================================
    // Table Encoding Tests
    // ============================================

    public function testEncodeNestedArrayProducesTable(): void
    {
        // Arrange
        $data = [
            'database' => [
                'server' => '192.168.1.1',
                'port' => 5432,
            ],
        ];

        // Act
        $toml = (string) Toml::encode($data);

        // Assert
        self::assertStringContainsString('[database]', $toml);
        self::assertStringContainsString('server = ', $toml);
        self::assertStringContainsString('port = 5432', $toml);
    }

    // ============================================
    // Array Encoding Tests
    // ============================================

    public function testEncodeSimpleArrayProducesArrayValue(): void
    {
        // Arrange
        $data = ['ports' => [8000, 8001, 8002]];

        // Act
        $toml = (string) Toml::encode($data);

        // Assert
        self::assertStringContainsString('ports = [', $toml);
        self::assertStringContainsString('8000', $toml);
        self::assertStringContainsString('8001', $toml);
        self::assertStringContainsString('8002', $toml);
    }

    // ============================================
    // Table Array Encoding Tests
    // ============================================

    public function testEncodeTableArrayProducesDoubleBracketNotation(): void
    {
        // Arrange
        $data = [
            'products' => [
                ['name' => 'Hammer', 'sku' => 123],
                ['name' => 'Nail', 'sku' => 456],
            ],
        ];

        // Act
        $toml = (string) Toml::encode($data);

        // Assert
        self::assertStringContainsString('[[products]]', $toml);
        self::assertStringContainsString('name = ', $toml);
        self::assertStringContainsString('Hammer', $toml);
        self::assertStringContainsString('Nail', $toml);
    }

    // ============================================
    // Round-Trip Tests
    // ============================================

    #[DataProvider('provideRoundTripData')]
    public function testEncodeAndParseRoundTrip(array $originalData): void
    {
        // Act
        $encoded = (string) Toml::encode($originalData);
        $parsed = Toml::parseToArray($encoded);

        // Assert
        self::assertEquals($originalData, $parsed);
    }

    // ============================================
    // DateTime Encoding Tests
    // ============================================

    public function testEncodeDateTimeWithUTCProducesZFormat(): void
    {
        // Arrange
        $data = ['created' => new \DateTimeImmutable('1979-05-27T07:32:00Z')];

        // Act
        $toml = (string) Toml::encode($data);

        // Assert
        self::assertStringContainsString('1979-05-27T07:32:00Z', $toml);
    }

    public function testEncodeDateTimeWithOffsetProducesOffsetFormat(): void
    {
        // Arrange
        $data = ['created' => new \DateTimeImmutable('2024-01-15T10:30:00+03:00')];

        // Act
        $toml = (string) Toml::encode($data);

        // Assert
        self::assertStringContainsString('2024-01-15T10:30:00+03:00', $toml);
    }

    // ============================================
    // JsonSerializable Support Tests
    // ============================================

    public function testEncodeJsonSerializableObject(): void
    {
        // Arrange
        $object = new class implements \JsonSerializable {
            public function jsonSerialize(): array
            {
                return ['name' => 'Test', 'value' => 123];
            }
        };

        // Act
        $toml = (string) Toml::encode($object);

        // Assert
        self::assertStringContainsString('name = ', $toml);
        self::assertStringContainsString('Test', $toml);
        self::assertStringContainsString('value = 123', $toml);
    }

    // ============================================
    // Complex Structure Tests
    // ============================================

    public function testEncodeComplexStructure(): void
    {
        // Arrange
        $data = [
            'title' => 'TOML Example',
            'owner' => [
                'name' => 'Tom Preston-Werner',
                'dob' => new \DateTimeImmutable('1979-05-27T07:32:00Z'),
            ],
            'database' => [
                'server' => '192.168.1.1',
                'ports' => [8000, 8001, 8002],
                'connection_max' => 5000,
                'enabled' => true,
            ],
            'products' => [
                ['name' => 'Hammer', 'sku' => 738594937],
                ['name' => 'Nail', 'sku' => 284758393],
            ],
        ];

        // Act
        $toml = (string) Toml::encode($data);

        // Assert - Check for all major sections
        self::assertStringContainsString('title = ', $toml);
        self::assertStringContainsString('[owner]', $toml);
        self::assertStringContainsString('[database]', $toml);
        self::assertStringContainsString('[[products]]', $toml);
        self::assertStringContainsString('Tom Preston-Werner', $toml);
        self::assertStringContainsString('1979-05-27T07:32:00Z', $toml);
        self::assertStringContainsString('Hammer', $toml);
        self::assertStringContainsString('Nail', $toml);
    }

    public function testEncodeComplexStructureRoundTrip(): void
    {
        // Arrange
        $originalData = [
            'title' => 'TOML Example',
            'owner' => [
                'name' => 'Tom Preston-Werner',
                'dob' => new \DateTimeImmutable('1979-05-27T07:32:00Z'),
            ],
            'database' => [
                'server' => '192.168.1.1',
                'ports' => [8000, 8001, 8002],
                'connection_max' => 5000,
                'enabled' => true,
            ],
            'products' => [
                ['name' => 'Hammer', 'sku' => 738594937],
                ['name' => 'Nail', 'sku' => 284758393],
            ],
        ];

        // Act
        $encoded = (string) Toml::encode($originalData);
        $parsed = Toml::parseToArray($encoded);

        // Assert - Structure matches
        self::assertArrayHasKey('title', $parsed);
        self::assertArrayHasKey('owner', $parsed);
        self::assertArrayHasKey('database', $parsed);
        self::assertArrayHasKey('products', $parsed);

        self::assertSame('TOML Example', $parsed['title']);
        self::assertSame('Tom Preston-Werner', $parsed['owner']['name']);
        self::assertSame('192.168.1.1', $parsed['database']['server']);
        self::assertCount(2, $parsed['products']);
        self::assertSame('Hammer', $parsed['products'][0]['name']);
        self::assertSame('Nail', $parsed['products'][1]['name']);
    }

    // ============================================
    // Edge Cases Tests
    // ============================================

    public function testEncodeEmptyArrayProducesEmptyToml(): void
    {
        // Arrange
        $data = [];

        // Act
        $toml = (string) Toml::encode($data);

        // Assert
        self::assertSame('', $toml);
    }

    public function testEncodeSpecialFloatValues(): void
    {
        // Arrange
        $data = [
            'infinity' => INF,
            'neg_infinity' => -INF,
            'not_a_number' => NAN,
        ];

        // Act
        $toml = (string) Toml::encode($data);

        // Assert
        self::assertStringContainsString('infinity = inf', $toml);
        self::assertStringContainsString('neg_infinity = -inf', $toml);
        self::assertStringContainsString('not_a_number = nan', $toml);
    }

    public function testEncodeStringWithNewlinesProducesMultilineString(): void
    {
        // Arrange
        $data = ['description' => "Line 1\nLine 2\nLine 3"];

        // Act
        $toml = (string) Toml::encode($data);

        // Assert
        // Should contain all three lines
        self::assertStringContainsString('Line 1', $toml);
        self::assertStringContainsString('Line 2', $toml);
        self::assertStringContainsString('Line 3', $toml);

        // Should contain actual newlines (multiline format)
        $lines = \explode("\n", $toml);
        self::assertGreaterThan(3, \count($lines), 'Should have multiple lines');
    }

    // ============================================
    // Fixture-based Round-Trip Tests
    // ============================================

    #[DataProvider('provideFixtureFiles')]
    public function testEncodeFixtureFileRoundTrip(string $filename): void
    {
        // Arrange
        $fixturePath = __DIR__ . '/fixtures/' . $filename;
        $tomlContent = \file_get_contents($fixturePath);
        self::assertNotFalse($tomlContent, "Failed to read fixture file: {$filename}");

        // Parse original TOML to array
        $originalArray = Toml::parseToArray($tomlContent);

        // Act
        // Encode array back to TOML string
        $encodedToml = (string) Toml::encode($originalArray);

        // Parse the encoded TOML back to array
        $reEncodedArray = Toml::parseToArray($encodedToml);

        // Assert
        // The re-encoded array should match the original parsed array
        self::assertEquals(
            $originalArray,
            $reEncodedArray,
            "Round-trip encoding/decoding changed the data structure for {$filename}"
        );
    }
}
