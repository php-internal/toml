<?php

declare(strict_types=1);

namespace Internal\Toml\Tests\Unit\Node\Value;

use Internal\Toml\Toml;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Internal\Toml\Node\Value\StringValue;
use Internal\Toml\Node\Value\IntegerValue;
use Internal\Toml\Node\Value\FloatValue;
use Internal\Toml\Node\Value\BooleanValue;
use Internal\Toml\Node\Value\DateTimeValue;
use Internal\Toml\Node\Value\LocalTimeValue;
use Internal\Toml\Node\Value\ArrayValue;
use Internal\Toml\Node\Value\InlineTableValue;

#[CoversClass(StringValue::class)]
#[CoversClass(IntegerValue::class)]
#[CoversClass(FloatValue::class)]
#[CoversClass(BooleanValue::class)]
#[CoversClass(DateTimeValue::class)]
#[CoversClass(LocalTimeValue::class)]
#[CoversClass(ArrayValue::class)]
#[CoversClass(InlineTableValue::class)]
#[Group('serialization')]
final class ValueSerializationTest extends TestCase
{
    public static function provideStringValues(): \Generator
    {
        yield 'basic string' => ['str = "hello"', '"hello"'];
        yield 'string with escapes' => ['str = "hello\\nworld"', '\\n'];
        yield 'literal string' => ["str = 'C:\\path'", "'C:\\path'"];
        yield 'multiline basic' => [
            <<<'TOML'
            str = """
            hello
            world"""
            TOML,
            '"""',
        ];
    }

    public static function provideIntegerValues(): \Generator
    {
        yield 'decimal' => ['num = 42', '42'];
        yield 'hex' => ['num = 0xDEAD', '0xDEAD'];
        yield 'octal' => ['num = 0o755', '0o755'];
        yield 'binary' => ['num = 0b1010', '0b1010'];
        yield 'with underscores' => ['num = 1_000_000', '1_000_000'];
    }

    public static function provideFloatValues(): \Generator
    {
        yield 'simple float' => ['flt = 3.14', '3.14'];
        yield 'scientific notation' => ['flt = 1e6', '1e6'];
        yield 'negative exponent' => ['flt = 6.626e-34', '6.626e-34'];
        yield 'with underscores' => ['flt = 1_000.5', '1_000.5'];
    }

    public static function provideDateTimeValues(): \Generator
    {
        yield 'offset datetime' => ['dt = 1979-05-27T07:32:00Z', '1979-05-27T07:32:00Z'];
        yield 'local datetime' => ['dt = 1979-05-27T07:32:00', '1979-05-27T07:32:00'];
        yield 'local date' => ['dt = 1979-05-27', '1979-05-27'];
        yield 'local time' => ['dt = 07:32:00', '07:32:00'];
        yield 'with milliseconds' => ['dt = 1979-05-27T00:32:00.999999', '1979-05-27T00:32:00.999999'];
    }

    public static function provideStringEscapeExamples(): \Generator
    {
        yield 'newline' => ["hello\\nworld", "\\n"];
        yield 'tab' => ["hello\\tworld", "\\t"];
        yield 'quote' => ['say \\"hello\\"', '\\"'];
        yield 'backslash' => ['path\\\\to\\\\file', '\\\\'];
    }
    // ============================================
    // String Value Serialization Tests
    // ============================================

    #[DataProvider('provideStringValues')]
    public function testStringValueToString(string $toml, string $expected): void
    {
        // Arrange
        $document = Toml::parse($toml);
        $entry = $document->findEntry('str');

        // Act
        $result = (string) $entry->value;

        // Assert
        self::assertStringContainsString($expected, $result);
    }

    // ============================================
    // Integer Value Serialization Tests
    // ============================================

    #[DataProvider('provideIntegerValues')]
    public function testIntegerValueToString(string $toml, string $expected): void
    {
        // Arrange
        $document = Toml::parse($toml);
        $entry = $document->findEntry('num');

        // Act
        $result = (string) $entry->value;

        // Assert
        self::assertSame($expected, $result);
    }

    // ============================================
    // Float Value Serialization Tests
    // ============================================

    #[DataProvider('provideFloatValues')]
    public function testFloatValueToString(string $toml, string $expected): void
    {
        // Arrange
        $document = Toml::parse($toml);
        $entry = $document->findEntry('flt');

        // Act
        $result = (string) $entry->value;

        // Assert
        self::assertSame($expected, $result);
    }

    // ============================================
    // Boolean Value Serialization Tests
    // ============================================

    public function testBooleanTrueToString(): void
    {
        // Arrange
        $document = Toml::parse('flag = true');
        $entry = $document->findEntry('flag');

        // Act
        $result = (string) $entry->value;

        // Assert
        self::assertSame('true', $result);
    }

    public function testBooleanFalseToString(): void
    {
        // Arrange
        $document = Toml::parse('flag = false');
        $entry = $document->findEntry('flag');

        // Act
        $result = (string) $entry->value;

        // Assert
        self::assertSame('false', $result);
    }

    // ============================================
    // DateTime Value Serialization Tests
    // ============================================

    #[DataProvider('provideDateTimeValues')]
    public function testDateTimeValueToString(string $toml, string $expected): void
    {
        // Arrange
        $document = Toml::parse($toml);
        $entry = $document->findEntry('dt');

        // Act
        $result = (string) $entry->value;

        // Assert
        self::assertSame($expected, $result);
    }

    // ============================================
    // Array Value Serialization Tests
    // ============================================

    public function testArrayValueToStringEmpty(): void
    {
        // Arrange
        $document = Toml::parse('arr = []');
        $entry = $document->findEntry('arr');

        // Act
        $result = (string) $entry->value;

        // Assert
        self::assertSame('[]', $result);
    }

    public function testArrayValueToStringSimple(): void
    {
        // Arrange
        $document = Toml::parse('arr = [1, 2, 3]');
        $entry = $document->findEntry('arr');

        // Act
        $result = (string) $entry->value;

        // Assert
        self::assertSame('[1, 2, 3]', $result);
    }

    public function testArrayValueToStringNested(): void
    {
        // Arrange
        $document = Toml::parse('arr = [[1, 2], [3, 4]]');
        $entry = $document->findEntry('arr');

        // Act
        $result = (string) $entry->value;

        // Assert
        self::assertSame('[[1, 2], [3, 4]]', $result);
    }

    public function testArrayValueToStringMixed(): void
    {
        // Arrange
        $document = Toml::parse('arr = [1, "two", true]');
        $entry = $document->findEntry('arr');

        // Act
        $result = (string) $entry->value;

        // Assert
        self::assertSame('[1, "two", true]', $result);
    }

    // ============================================
    // Inline Table Value Serialization Tests
    // ============================================

    public function testInlineTableValueToStringEmpty(): void
    {
        // Arrange
        $document = Toml::parse('tbl = {}');
        $entry = $document->findEntry('tbl');

        // Act
        $result = (string) $entry->value;

        // Assert
        self::assertSame('{}', $result);
    }

    public function testInlineTableValueToStringSimple(): void
    {
        // Arrange
        $document = Toml::parse('tbl = {x = 1, y = 2}');
        $entry = $document->findEntry('tbl');

        // Act
        $result = (string) $entry->value;

        // Assert
        self::assertSame('{x = 1, y = 2}', $result);
    }

    public function testInlineTableValueToStringNested(): void
    {
        // Arrange
        $document = Toml::parse('tbl = {a = {b = 1}}');
        $entry = $document->findEntry('tbl');

        // Act
        $result = (string) $entry->value;

        // Assert
        self::assertStringContainsString('a = {b = 1}', $result);
    }

    // ============================================
    // String Escaping Tests
    // ============================================

    #[DataProvider('provideStringEscapeExamples')]
    public function testStringValueEscapesCorrectly(string $input, string $expectedEscape): void
    {
        // Arrange
        $toml = 'str = "' . $input . '"';
        $document = Toml::parse($toml);
        $entry = $document->findEntry('str');

        // Act
        $result = (string) $entry->value;

        // Assert
        self::assertStringContainsString($expectedEscape, $result);
    }
}
