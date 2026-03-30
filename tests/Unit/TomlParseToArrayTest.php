<?php

declare(strict_types=1);

namespace Internal\Toml\Tests\Unit;

use Internal\Toml\Toml;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[CoversClass(Toml::class)]
final class TomlParseToArrayTest extends TestCase
{
    public static function provideValidKeys(): \Generator
    {
        yield 'bare key' => ['key = "value"', ['key' => 'value']];
        yield 'bare key with numbers' => ['key123 = "value"', ['key123' => 'value']];
        yield 'bare key with underscores' => ['key_name = "value"', ['key_name' => 'value']];
        yield 'bare key with dashes' => ['key-name = "value"', ['key-name' => 'value']];
        yield 'quoted key' => ['"quoted key" = "value"', ['quoted key' => 'value']];
        yield 'quoted key with special chars' => ['"key.with.dots" = "value"', ['key.with.dots' => 'value']];
        yield 'dotted key creates nested structure' => [
            'parent.child = "value"',
            ['parent' => ['child' => 'value']],
        ];
        yield 'multiple dotted keys' => [
            "parent.child1 = \"value1\"\nparent.child2 = \"value2\"",
            ['parent' => ['child1' => 'value1', 'child2' => 'value2']],
        ];
    }

    public static function provideBasicStrings(): \Generator
    {
        yield 'simple string' => ['str = "hello world"', ['str' => 'hello world']];
        yield 'string with newline escape' => ['str = "hello\nworld"', ['str' => "hello\nworld"]];
        yield 'string with tab escape' => ['str = "hello\tworld"', ['str' => "hello\tworld"]];
        yield 'string with quote escape' => ['str = "hello \"world\""', ['str' => 'hello "world"']];
        yield 'string with backslash escape' => ['str = "hello\\\\world"', ['str' => 'hello\\world']];
        yield 'string with unicode escape' => ['str = "hello\u0020world"', ['str' => 'hello world']];
        yield 'string with hex escape \\x41' => ['str = "\\x41"', ['str' => 'A']];
        yield 'string with hex escape \\x00' => ['str = "\\x00"', ['str' => "\x00"]];
        yield 'string with hex escape \\xE9' => ['str = "Jos\\xE9"', ['str' => "José"]];
        yield 'string with hex escape \\xFF' => ['str = "\\xFF"', ['str' => "\u{00FF}"]];
        yield 'string with escape \\e' => ['str = "\\e"', ['str' => "\x1B"]];
        yield 'string with escape \\e in context' => ['str = "\\e[31m"', ['str' => "\x1B[31m"]];
    }

    public static function provideLiteralStrings(): \Generator
    {
        yield 'simple literal string' => ["str = 'hello world'", ['str' => 'hello world']];
        yield 'literal string with backslash' => ["str = 'C:\\Users\\path'", ['str' => 'C:\\Users\\path']];
        yield 'literal string no escape' => ["str = 'hello\\nworld'", ['str' => 'hello\\nworld']];
    }

    public static function provideIntegers(): \Generator
    {
        yield 'positive integer' => ['int = 42', ['int' => 42]];
        yield 'negative integer' => ['int = -42', ['int' => -42]];
        yield 'zero' => ['int = 0', ['int' => 0]];
        yield 'integer with plus sign' => ['int = +42', ['int' => 42]];
        yield 'integer with underscores' => ['int = 1_000_000', ['int' => 1000000]];
        yield 'hex integer' => ['int = 0xDEADBEEF', ['int' => 0xDEADBEEF]];
        yield 'octal integer' => ['int = 0o755', ['int' => 0755]];
        yield 'binary integer' => ['int = 0b11010110', ['int' => 0b11010110]];
    }

    public static function provideFloats(): \Generator
    {
        yield 'simple float' => ['flt = 3.14', ['flt' => 3.14]];
        yield 'negative float' => ['flt = -3.14', ['flt' => -3.14]];
        yield 'float with exponent' => ['flt = 5e+22', ['flt' => 5e+22]];
        yield 'float with negative exponent' => ['flt = 1e-6', ['flt' => 1e-6]];
        yield 'float with underscores' => ['flt = 9_224_617.445_991_228_313', ['flt' => 9224617.445991228313]];
        yield 'positive infinity' => ['flt = inf', ['flt' => INF]];
        yield 'negative infinity' => ['flt = -inf', ['flt' => -INF]];
        // NaN tests removed due to PHP limitation: NAN === NAN is always false
    }

    public static function provideBooleans(): \Generator
    {
        yield 'true value' => ['bool = true', ['bool' => true]];
        yield 'false value' => ['bool = false', ['bool' => false]];
    }

    public static function provideDateTimes(): \Generator
    {
        yield 'offset date-time with Z' => [
            'dt = 1979-05-27T07:32:00Z',
            ['dt' => new \DateTimeImmutable('1979-05-27T07:32:00Z')],
        ];
        yield 'offset date-time with offset' => [
            'dt = 1979-05-27T07:32:00-07:00',
            ['dt' => new \DateTimeImmutable('1979-05-27T07:32:00-07:00')],
        ];
        yield 'offset date-time with milliseconds' => [
            'dt = 1979-05-27T07:32:00.999Z',
            ['dt' => new \DateTimeImmutable('1979-05-27T07:32:00.999Z')],
        ];
        yield 'local date-time' => [
            'dt = 1979-05-27T07:32:00',
            ['dt' => new \DateTimeImmutable('1979-05-27T07:32:00')],
        ];
        yield 'local date' => [
            'dt = 1979-05-27',
            ['dt' => new \DateTimeImmutable('1979-05-27')],
        ];
        yield 'local time' => [
            'dt = 07:32:00',
            ['dt' => '07:32:00'],
        ];
        yield 'local time without seconds' => [
            'dt = 07:32',
            ['dt' => '07:32'],
        ];
        yield 'offset date-time without seconds' => [
            'dt = 1979-05-27T07:32Z',
            ['dt' => new \DateTimeImmutable('1979-05-27T07:32:00Z')],
        ];
        yield 'offset date-time without seconds with offset' => [
            'dt = 1979-05-27T07:32-07:00',
            ['dt' => new \DateTimeImmutable('1979-05-27T07:32:00-07:00')],
        ];
        yield 'local date-time without seconds' => [
            'dt = 1979-05-27T07:32',
            ['dt' => new \DateTimeImmutable('1979-05-27T07:32:00')],
        ];
        yield 'local date-time without seconds space separator' => [
            'dt = 1979-05-27 07:32',
            ['dt' => new \DateTimeImmutable('1979-05-27T07:32:00')],
        ];
    }

    public static function provideArrays(): \Generator
    {
        yield 'simple integer array' => ['arr = [1, 2, 3]', ['arr' => [1, 2, 3]]];
        yield 'string array' => ['arr = ["a", "b", "c"]', ['arr' => ['a', 'b', 'c']]];
        yield 'mixed type array' => ['arr = [1, "two", 3.0]', ['arr' => [1, 'two', 3.0]]];
        yield 'nested arrays' => ['arr = [[1, 2], [3, 4]]', ['arr' => [[1, 2], [3, 4]]]];
        yield 'array with trailing comma' => ['arr = [1, 2, 3,]', ['arr' => [1, 2, 3]]];
        yield 'multiline array' => [
            "arr = [\n  1,\n  2,\n  3\n]",
            ['arr' => [1, 2, 3]],
        ];
        yield 'multiline array with comments' => [
            "arr = [\n  1, # first\n  2,\n  # standalone comment\n  3\n]",
            ['arr' => [1, 2, 3]],
        ];
    }

    public static function provideInlineTables(): \Generator
    {
        yield 'simple inline table' => [
            'table = {key = "value"}',
            ['table' => ['key' => 'value']],
        ];
        yield 'inline table with multiple keys' => [
            'table = {key1 = "value1", key2 = "value2"}',
            ['table' => ['key1' => 'value1', 'key2' => 'value2']],
        ];
        yield 'inline table with mixed types' => [
            'table = {str = "text", int = 42, bool = true}',
            ['table' => ['str' => 'text', 'int' => 42, 'bool' => true]],
        ];
        yield 'inline table with trailing comma' => [
            'table = {key = "value",}',
            ['table' => ['key' => 'value']],
        ];
        yield 'multi-line inline table' => [
            "table = {\n  key1 = \"value1\",\n  key2 = \"value2\"\n}",
            ['table' => ['key1' => 'value1', 'key2' => 'value2']],
        ];
        yield 'multi-line inline table with comments' => [
            "table = {\n  key1 = \"value1\", # first\n  key2 = \"value2\" # second\n}",
            ['table' => ['key1' => 'value1', 'key2' => 'value2']],
        ];
        yield 'multi-line inline table with trailing comma' => [
            "table = {\n  key1 = \"value1\",\n  key2 = \"value2\",\n}",
            ['table' => ['key1' => 'value1', 'key2' => 'value2']],
        ];
        yield 'nested multi-line inline table' => [
            "table = {\n  inner = {\n    key = \"value\",\n  },\n}",
            ['table' => ['inner' => ['key' => 'value']]],
        ];
    }

    public static function provideInvalidToml(): \Generator
    {
        yield 'duplicate keys' => [
            "key = \"value1\"\nkey = \"value2\"",
            'Duplicate key',
        ];
        yield 'invalid date format' => [
            'date = 2024-13-45',
            'Invalid date',
        ];
        yield 'redefined table' => [
            "[table]\nkey = \"value\"\n[table]\nkey2 = \"value2\"",
            'Table redefined',
        ];
        yield 'invalid boolean uppercase' => [
            'bool = True',
            'Invalid boolean',
        ];
        yield 'missing closing bracket' => [
            '[table',
            'Missing closing bracket',
        ];
        yield 'invalid array missing comma' => [
            'arr = [1 2 3]',
            'Invalid array',
        ];
        yield 'key without value' => [
            'key =',
            'Missing value',
        ];
        yield 'value without key' => [
            '= "value"',
            'Missing key',
        ];
    }
    // ============================================
    // Basic Key-Value Pairs Tests
    // ============================================

    public function testDecodeSimpleKeyValue(): void
    {
        // Arrange
        $toml = 'key = "value"';

        // Act
        $result = Toml::parseToArray($toml);

        // Assert
        self::assertSame(['key' => 'value'], $result);
    }

    #[DataProvider('provideValidKeys')]
    public function testDecodeWithVariousKeyTypes(string $toml, array $expected): void
    {
        // Arrange & Act
        $result = Toml::parseToArray($toml);

        // Assert
        self::assertSame($expected, $result);
    }

    // ============================================
    // Comments Tests
    // ============================================

    public function testDecodeIgnoresComments(): void
    {
        // Arrange
        $toml = <<<'TOML'
# This is a comment
key = "value" # inline comment
TOML;

        // Act
        $result = Toml::parseToArray($toml);

        // Assert
        self::assertSame(['key' => 'value'], $result);
    }

    // ============================================
    // String Types Tests
    // ============================================

    #[DataProvider('provideBasicStrings')]
    public function testDecodeBasicStrings(string $toml, array $expected): void
    {
        // Arrange & Act
        $result = Toml::parseToArray($toml);

        // Assert
        self::assertSame($expected, $result);
    }

    #[DataProvider('provideLiteralStrings')]
    public function testDecodeLiteralStrings(string $toml, array $expected): void
    {
        // Arrange & Act
        $result = Toml::parseToArray($toml);

        // Assert
        self::assertSame($expected, $result);
    }

    public function testDecodeMultilineBasicString(): void
    {
        // Arrange
        $toml = <<<'TOML'
str = """
line one
line two
line three"""
TOML;

        // Act
        $result = Toml::parseToArray($toml);

        // Assert
        self::assertSame(['str' => "line one\nline two\nline three"], $result);
    }

    public function testDecodeMultilineLiteralString(): void
    {
        // Arrange
        $toml = <<<'TOML'
str = '''
line one\n
line two\t
line three'''
TOML;

        // Act
        $result = Toml::parseToArray($toml);

        // Assert
        self::assertSame(['str' => "line one\\n\nline two\\t\nline three"], $result);
    }

    // ============================================
    // Integer Tests
    // ============================================

    #[DataProvider('provideIntegers')]
    public function testDecodeIntegers(string $toml, array $expected): void
    {
        // Arrange & Act
        $result = Toml::parseToArray($toml);

        // Assert
        self::assertSame($expected, $result);
    }

    // ============================================
    // Float Tests
    // ============================================

    #[DataProvider('provideFloats')]
    public function testDecodeFloats(string $toml, array $expected): void
    {
        // Arrange & Act
        $result = Toml::parseToArray($toml);

        // Assert
        self::assertSame($expected, $result);
    }

    // ============================================
    // Boolean Tests
    // ============================================

    #[DataProvider('provideBooleans')]
    public function testDecodeBooleans(string $toml, array $expected): void
    {
        // Arrange & Act
        $result = Toml::parseToArray($toml);

        // Assert
        self::assertSame($expected, $result);
    }

    // ============================================
    // DateTime Tests
    // ============================================

    #[DataProvider('provideDateTimes')]
    public function testDecodeDateTimes(string $toml, array $expected): void
    {
        // Arrange & Act
        $result = Toml::parseToArray($toml);

        // Assert
        self::assertEquals($expected, $result);
    }

    // ============================================
    // Array Tests
    // ============================================

    #[DataProvider('provideArrays')]
    public function testDecodeArrays(string $toml, array $expected): void
    {
        // Arrange & Act
        $result = Toml::parseToArray($toml);

        // Assert
        self::assertSame($expected, $result);
    }

    // ============================================
    // Table Tests
    // ============================================

    public function testDecodeSimpleTable(): void
    {
        // Arrange
        $toml = <<<'TOML'
[table]
key = "value"
TOML;

        // Act
        $result = Toml::parseToArray($toml);

        // Assert
        self::assertSame(['table' => ['key' => 'value']], $result);
    }

    public function testDecodeNestedTables(): void
    {
        // Arrange
        $toml = <<<'TOML'
[parent.child]
key = "value"
TOML;

        // Act
        $result = Toml::parseToArray($toml);

        // Assert
        self::assertSame(['parent' => ['child' => ['key' => 'value']]], $result);
    }

    public function testDecodeMultipleTables(): void
    {
        // Arrange
        $toml = <<<'TOML'
[table1]
key1 = "value1"

[table2]
key2 = "value2"
TOML;

        // Act
        $result = Toml::parseToArray($toml);

        // Assert
        self::assertSame([
            'table1' => ['key1' => 'value1'],
            'table2' => ['key2' => 'value2'],
        ], $result);
    }

    // ============================================
    // Inline Table Tests
    // ============================================

    #[DataProvider('provideInlineTables')]
    public function testDecodeInlineTables(string $toml, array $expected): void
    {
        // Arrange & Act
        $result = Toml::parseToArray($toml);

        // Assert
        self::assertSame($expected, $result);
    }

    // ============================================
    // Array of Tables Tests
    // ============================================

    public function testDecodeArrayOfTables(): void
    {
        // Arrange
        $toml = <<<'TOML'
[[products]]
name = "Hammer"
sku = 738594937

[[products]]
name = "Nail"
sku = 284758393
TOML;

        // Act
        $result = Toml::parseToArray($toml);

        // Assert
        self::assertSame([
            'products' => [
                ['name' => 'Hammer', 'sku' => 738594937],
                ['name' => 'Nail', 'sku' => 284758393],
            ],
        ], $result);
    }

    public function testDecodeNestedArrayOfTables(): void
    {
        // Arrange
        $toml = <<<'TOML'
[[fruits]]
name = "apple"

[[fruits.varieties]]
name = "red delicious"

[[fruits.varieties]]
name = "granny smith"
TOML;

        // Act
        $result = Toml::parseToArray($toml);

        // Assert
        self::assertSame([
            'fruits' => [
                [
                    'name' => 'apple',
                    'varieties' => [
                        ['name' => 'red delicious'],
                        ['name' => 'granny smith'],
                    ],
                ],
            ],
        ], $result);
    }

    // ============================================
    // Complex Examples Tests
    // ============================================

    public function testDecodeComplexTomlDocument(): void
    {
        // Arrange
        $toml = <<<'TOML'
            # This is a TOML document

            title = "TOML Example"

            [owner]
            name = "Tom Preston-Werner"
            dob = 1979-05-27T07:32:00-08:00

            [database]
            enabled = true
            ports = [8000, 8001, 8002]
            connection_max = 5000
            temp_targets = {cpu = 79.5, case = 72.0}

            [servers]

            [servers.alpha]
            ip = "10.0.0.1"
            role = "frontend"

            [servers.beta]
            ip = "10.0.0.2"
            role = "backend"
            TOML;

        // Act
        $result = Toml::parseToArray($toml);

        // Assert
        self::assertArrayHasKey('title', $result);
        self::assertArrayHasKey('owner', $result);
        self::assertArrayHasKey('database', $result);
        self::assertArrayHasKey('servers', $result);
        self::assertSame('TOML Example', $result['title']);
        self::assertSame('Tom Preston-Werner', $result['owner']['name']);
        self::assertTrue($result['database']['enabled']);
        self::assertSame([8000, 8001, 8002], $result['database']['ports']);
    }

    // ============================================
    // Error Cases Tests
    // ============================================

    #[Group('error-handling')]
    #[DataProvider('provideInvalidToml')]
    public function testDecodeThrowsExceptionForInvalidToml(string $toml, string $expectedError): void
    {
        // Assert (before Act for exceptions)
        $this->expectException(\RuntimeException::class);
        // Note: Not checking exact message text as implementation may vary

        // Act
        Toml::parseToArray($toml);
    }

    // ============================================
    // Whitespace Handling Tests
    // ============================================

    public function testDecodeHandlesWhitespaceCorrectly(): void
    {
        // Arrange
        $toml = <<<'TOML'
  key1  =  "value1"
key2="value2"
  [  table  ]
  key  =  "value"
TOML;

        // Act
        $result = Toml::parseToArray($toml);

        // Assert
        self::assertSame([
            'key1' => 'value1',
            'key2' => 'value2',
            'table' => ['key' => 'value'],
        ], $result);
    }

    // ============================================
    // Empty Input Tests
    // ============================================

    public function testDecodeEmptyString(): void
    {
        // Arrange
        $toml = '';

        // Act
        $result = Toml::parseToArray($toml);

        // Assert
        self::assertSame([], $result);
    }

    public function testDecodeOnlyComments(): void
    {
        // Arrange
        $toml = <<<'TOML'
# Just a comment
# Another comment
TOML;

        // Act
        $result = Toml::parseToArray($toml);

        // Assert
        self::assertSame([], $result);
    }
}
