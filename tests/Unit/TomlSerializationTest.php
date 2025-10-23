<?php

declare(strict_types=1);

namespace Internal\Toml\Tests\Unit;

use Internal\Toml\Node\Document;
use Internal\Toml\Toml;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[CoversClass(Toml::class)]
#[CoversClass(Document::class)]
#[Group('serialization')]
final class TomlSerializationTest extends TestCase
{
    public static function provideRoundTripExamples(): \Generator
    {
        yield 'simple key-value' => ['key = "value"'];
        yield 'multiple entries' => [
            <<<'TOML'
            name = "John"
            age = 30
            active = true
            TOML,
        ];
        yield 'table' => [
            <<<'TOML'
            [database]
            host = "localhost"
            port = 5432
            TOML,
        ];
        yield 'table array' => [
            <<<'TOML'
            [[products]]
            name = "Hammer"
            sku = 123

            [[products]]
            name = "Nail"
            sku = 456
            TOML,
        ];
        yield 'nested tables' => [
            <<<'TOML'
            [server]
            host = "localhost"

            [server.database]
            name = "mydb"
            TOML,
        ];
    }

    public static function provideFormatPreservationExamples(): \Generator
    {
        yield 'decimal integer' => ['int = 42'];
        yield 'hex integer' => ['int = 0xDEAD'];
        yield 'octal integer' => ['int = 0o755'];
        yield 'binary integer' => ['int = 0b1101'];
        yield 'integer with underscores' => ['int = 1_000_000'];
        yield 'basic string' => ['str = "hello"'];
        yield 'literal string' => ["str = 'C:\\Users\\path'"];
        yield 'float' => ['flt = 3.14'];
        yield 'float with exponent' => ['flt = 1e6'];
        yield 'boolean true' => ['flag = true'];
        yield 'boolean false' => ['flag = false'];
    }

    public static function provideDateTimeExamples(): \Generator
    {
        yield 'offset datetime' => ['dt = 1979-05-27T07:32:00Z'];
        yield 'local datetime' => ['dt = 1979-05-27T07:32:00'];
        yield 'local date' => ['dt = 1979-05-27'];
        yield 'local time' => ['dt = 07:32:00'];
    }
    // ============================================
    // Basic Serialization Tests
    // ============================================

    public function testToStringReturnsStringFromDocument(): void
    {
        // Arrange
        $toml = 'key = "value"';
        $document = Toml::parse($toml);

        // Act
        $result = $document->__toString();

        // Assert
        self::assertIsString($result);
        self::assertStringContainsString('key = "value"', $result);
    }

    public function testToStringEmptyDocumentReturnsEmptyString(): void
    {
        // Arrange
        $toml = '';
        $document = Toml::parse($toml);

        // Act
        $result = $document->__toString();

        // Assert
        self::assertSame('', $result);
    }

    public function testDocumentToStringCastWorks(): void
    {
        // Arrange
        $toml = 'name = "Test"';
        $document = Toml::parse($toml);

        // Act
        $result = (string) $document;

        // Assert
        self::assertIsString($result);
        self::assertStringContainsString('name = "Test"', $result);
    }

    // ============================================
    // Round-trip Tests
    // ============================================

    #[DataProvider('provideRoundTripExamples')]
    public function testRoundTripPreservesData(string $toml): void
    {
        // Arrange
        $originalDoc = Toml::parse($toml);
        $originalArray = $originalDoc->toArray();

        // Act
        $serialized = $originalDoc->__toString();
        $roundTripDoc = Toml::parse($serialized);
        $roundTripArray = $roundTripDoc->toArray();

        // Assert
        self::assertEquals($originalArray, $roundTripArray, 'Round-trip should preserve data structure');
    }

    // ============================================
    // Format Preservation Tests
    // ============================================

    #[DataProvider('provideFormatPreservationExamples')]
    public function testToStringPreservesOriginalFormat(string $toml): void
    {
        // Arrange
        $document = Toml::parse($toml);

        // Act
        $serialized = $document->__toString();

        // Assert
        self::assertSame(\trim($toml), \trim($serialized), 'Serialization should preserve original format');
    }

    // ============================================
    // Comment Preservation Tests
    // ============================================

    public function testSerializePreservesStandaloneComments(): void
    {
        // Arrange
        $toml = <<<'TOML'
        # This is a comment
        key = "value"
        TOML;
        $document = Toml::parse($toml);

        // Act
        $serialized = $document->__toString();

        // Assert
        self::assertStringContainsString('# This is a comment', $serialized);
        self::assertStringContainsString('key = "value"', $serialized);
    }

    public function testSerializePreservesInlineComments(): void
    {
        // Arrange
        $toml = 'key = "value" # inline comment';
        $document = Toml::parse($toml);

        // Act
        $serialized = $document->__toString();

        // Assert
        self::assertStringContainsString('# inline comment', $serialized);
    }

    public function testSerializePreservesMultipleComments(): void
    {
        // Arrange
        $toml = <<<'TOML'
        # Comment 1
        # Comment 2
        key = "value"
        TOML;
        $document = Toml::parse($toml);

        // Act
        $serialized = $document->__toString();

        // Assert
        self::assertStringContainsString('# Comment 1', $serialized);
        self::assertStringContainsString('# Comment 2', $serialized);
    }

    // ============================================
    // Value Type Serialization Tests
    // ============================================

    public function testSerializeArrayValue(): void
    {
        // Arrange
        $toml = 'arr = [1, 2, 3]';
        $document = Toml::parse($toml);

        // Act
        $serialized = $document->__toString();

        // Assert
        self::assertStringContainsString('arr = [1, 2, 3]', $serialized);
    }

    public function testSerializeEmptyArray(): void
    {
        // Arrange
        $toml = 'arr = []';
        $document = Toml::parse($toml);

        // Act
        $serialized = $document->__toString();

        // Assert
        self::assertStringContainsString('arr = []', $serialized);
    }

    public function testSerializeNestedArray(): void
    {
        // Arrange
        $toml = 'matrix = [[1, 2], [3, 4]]';
        $document = Toml::parse($toml);

        // Act
        $serialized = $document->__toString();

        // Assert
        self::assertStringContainsString('matrix = [[1, 2], [3, 4]]', $serialized);
    }

    public function testSerializeInlineTable(): void
    {
        // Arrange
        $toml = 'point = {x = 1, y = 2}';
        $document = Toml::parse($toml);

        // Act
        $serialized = $document->__toString();

        // Assert
        self::assertStringContainsString('point = {x = 1, y = 2}', $serialized);
    }

    public function testSerializeEmptyInlineTable(): void
    {
        // Arrange
        $toml = 'tbl = {}';
        $document = Toml::parse($toml);

        // Act
        $serialized = $document->__toString();

        // Assert
        self::assertStringContainsString('tbl = {}', $serialized);
    }

    #[DataProvider('provideDateTimeExamples')]
    public function testSerializeDateTimeValues(string $toml): void
    {
        // Arrange
        $document = Toml::parse($toml);

        // Act
        $serialized = $document->__toString();

        // Assert
        self::assertSame(\trim($toml), \trim($serialized));
    }

    // ============================================
    // String Escaping Tests
    // ============================================

    public function testSerializeEscapesBasicStrings(): void
    {
        // Arrange
        $toml = 'str = "Hello\\nWorld"';
        $document = Toml::parse($toml);

        // Act
        $serialized = $document->__toString();

        // Assert
        self::assertStringContainsString('Hello\\n', $serialized);
    }

    public function testSerializePreservesLiteralStrings(): void
    {
        // Arrange
        $toml = "str = 'C:\\Users\\path'";
        $document = Toml::parse($toml);

        // Act
        $serialized = $document->__toString();

        // Assert
        self::assertStringContainsString("'C:\\Users\\path'", $serialized);
    }

    public function testSerializeMultilineBasicString(): void
    {
        // Arrange
        $toml = <<<'TOML'
        str = """
        Line 1
        Line 2"""
        TOML;
        $document = Toml::parse($toml);

        // Act
        $serialized = $document->__toString();

        // Assert
        self::assertStringContainsString('"""', $serialized);
        self::assertStringContainsString('Line 1', $serialized);
    }

    // ============================================
    // Table Serialization Tests
    // ============================================

    public function testSerializeTable(): void
    {
        // Arrange
        $toml = <<<'TOML'
        [database]
        host = "localhost"
        port = 5432
        TOML;
        $document = Toml::parse($toml);

        // Act
        $serialized = $document->__toString();

        // Assert
        self::assertStringContainsString('[database]', $serialized);
        self::assertStringContainsString('host = "localhost"', $serialized);
        self::assertStringContainsString('port = 5432', $serialized);
    }

    public function testSerializeNestedTables(): void
    {
        // Arrange
        $toml = <<<'TOML'
        [a.b]
        c = 1

        [a.b.d]
        e = 2
        TOML;
        $document = Toml::parse($toml);

        // Act
        $serialized = $document->__toString();

        // Assert
        self::assertStringContainsString('[a.b]', $serialized);
        self::assertStringContainsString('[a.b.d]', $serialized);
    }

    public function testSerializeTableArray(): void
    {
        // Arrange
        $toml = <<<'TOML'
        [[products]]
        name = "Hammer"
        sku = 123

        [[products]]
        name = "Nail"
        sku = 456
        TOML;
        $document = Toml::parse($toml);

        // Act
        $serialized = $document->__toString();

        // Assert
        self::assertStringContainsString('[[products]]', $serialized);
        self::assertStringContainsString('name = "Hammer"', $serialized);
        self::assertStringContainsString('name = "Nail"', $serialized);
    }

    // ============================================
    // Dotted Keys Tests
    // ============================================

    public function testSerializeDottedKey(): void
    {
        // Arrange
        $toml = 'a.b.c = "value"';
        $document = Toml::parse($toml);

        // Act
        $serialized = $document->__toString();

        // Assert
        self::assertStringContainsString('a.b.c = "value"', $serialized);
    }

    public function testSerializeQuotedDottedKey(): void
    {
        // Arrange
        $toml = '"a.b"."c.d" = "value"';
        $document = Toml::parse($toml);

        // Act
        $serialized = $document->__toString();

        // Assert
        self::assertStringContainsString('"a.b"."c.d"', $serialized);
    }

    // ============================================
    // Complex Structure Tests
    // ============================================

    public function testSerializeComplexDocument(): void
    {
        // Arrange
        $toml = <<<'TOML'
        # Configuration
        title = "Example"
        version = 1

        [database]
        host = "localhost"
        ports = [8001, 8002]

        [[servers]]
        name = "alpha"
        ip = "10.0.0.1"

        [[servers]]
        name = "beta"
        ip = "10.0.0.2"
        TOML;
        $document = Toml::parse($toml);

        // Act
        $serialized = $document->__toString();

        // Assert
        self::assertStringContainsString('# Configuration', $serialized);
        self::assertStringContainsString('[database]', $serialized);
        self::assertStringContainsString('[[servers]]', $serialized);

        // Verify round-trip
        $roundTrip = Toml::parse($serialized);
        self::assertEquals($document->toArray(), $roundTrip->toArray());
    }

    public function testSerializeRootEntriesBeforeTables(): void
    {
        // Arrange
        $toml = <<<'TOML'
        title = "Root"

        [table]
        key = "value"
        TOML;
        $document = Toml::parse($toml);

        // Act
        $serialized = $document->__toString();

        // Assert
        $titlePos = \strpos($serialized, 'title = "Root"');
        $tablePos = \strpos($serialized, '[table]');
        self::assertLessThan($tablePos, $titlePos, 'Root entries should appear before tables');
    }

    public function testSerializeAddsBlankLineBetweenTables(): void
    {
        // Arrange
        $toml = <<<'TOML'
        [table1]
        a = 1

        [table2]
        b = 2
        TOML;
        $document = Toml::parse($toml);

        // Act
        $serialized = $document->__toString();

        // Assert
        self::assertMatchesRegularExpression('/\[table1\].*\n.*\n\n\[table2\]/', $serialized);
    }
}
