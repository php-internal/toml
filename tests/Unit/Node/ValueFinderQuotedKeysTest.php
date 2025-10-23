<?php

declare(strict_types=1);

namespace Internal\Toml\Tests\Unit\Node;

use Internal\Toml\Toml;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(\Internal\Toml\Node\ValueFinder::class)]
#[CoversClass(\Internal\Toml\Node\Document::class)]
final class ValueFinderQuotedKeysTest extends TestCase
{
    public static function provideQuotedKeyScenarios(): \Generator
    {
        yield 'quoted key with emoji' => [
            '"🔑key" = "emoji value"',
            '"🔑key"',
            'emoji value',
        ];

        yield 'quoted key with unicode' => [
            '"Ключ" = "значение"',
            '"Ключ"',
            'значение',
        ];

        yield 'multiple quoted keys in path' => [
            <<<'TOML'
["first.part"]
["first.part"."second.part"]
value = "deeply nested"
TOML,
            '"first.part"."second.part".value',
            'deeply nested',
        ];

        yield 'quoted key followed by array index' => [
            '"items-list" = [1, 2, 3]',
            '"items-list".0',
            1,
        ];
    }

    public function testGetQuotedKeyWithDots(): void
    {
        // Arrange
        $toml = '"key.with.dots" = "value"';
        $doc = Toml::parse($toml);

        // Act
        $result = $doc->get('"key.with.dots"');

        // Assert
        self::assertSame('value', $result);
    }

    public function testGetQuotedKeyWithSpecialCharacters(): void
    {
        // Arrange
        $toml = '"special!@#$%^&*()chars" = "value"';
        $doc = Toml::parse($toml);

        // Act
        $result = $doc->get('"special!@#$%^&*()chars"');

        // Assert
        self::assertSame('value', $result);
    }

    public function testGetQuotedKeyWithSpaces(): void
    {
        // Arrange
        $toml = '"key with spaces" = "value"';
        $doc = Toml::parse($toml);

        // Act
        $result = $doc->get('"key with spaces"');

        // Assert
        self::assertSame('value', $result);
    }

    public function testGetMixedQuotedAndBareKeys(): void
    {
        // Arrange
        $toml = <<<'TOML'
[section]
"quoted.key" = "value1"
bare_key = "value2"
TOML;
        $doc = Toml::parse($toml);

        // Act
        $quoted = $doc->get('section."quoted.key"');
        $bare = $doc->get('section.bare_key');

        // Assert
        self::assertSame('value1', $quoted);
        self::assertSame('value2', $bare);
    }

    public function testGetNestedQuotedKeys(): void
    {
        // Arrange
        $toml = <<<'TOML'
["outer.key"]
"inner.key" = "nested value"
TOML;
        $doc = Toml::parse($toml);

        // Act
        $result = $doc->get('"outer.key"."inner.key"');

        // Assert
        self::assertSame('nested value', $result);
    }

    public function testGetQuotedKeyInTableArray(): void
    {
        // Arrange
        $toml = <<<'TOML'
[[products]]
"product.name" = "Item 1"
sku = 12345
TOML;
        $doc = Toml::parse($toml);

        // Act
        $result = $doc->get('products.0."product.name"');

        // Assert
        self::assertSame('Item 1', $result);
    }

    public function testGetQuotedKeyInInlineTable(): void
    {
        // Arrange
        // Note: TOML spec requires quoted keys in inline tables to use the actual key in storage
        // Our implementation converts to array, so dots in keys are treated as nested paths
        $toml = 'data = { "key-1" = "value1", "key-2" = "value2" }';
        $doc = Toml::parse($toml);

        // Act
        $value1 = $doc->get('data."key-1"');
        $value2 = $doc->get('data."key-2"');

        // Assert
        self::assertSame('value1', $value1);
        self::assertSame('value2', $value2);
    }

    public function testHasQuotedKey(): void
    {
        // Arrange
        $toml = '"key.with.dots" = "value"';
        $doc = Toml::parse($toml);

        // Act & Assert
        self::assertTrue($doc->has('"key.with.dots"'));
        self::assertFalse($doc->has('"nonexistent"'));
    }

    #[DataProvider('provideQuotedKeyScenarios')]
    public function testVariousQuotedKeyScenarios(string $toml, string $path, mixed $expected): void
    {
        // Arrange
        $doc = Toml::parse($toml);

        // Act
        $result = $doc->get($path);

        // Assert
        self::assertSame($expected, $result);
    }

    public function testComplexPathWithMixedQuoting(): void
    {
        // Arrange
        $toml = <<<'TOML'
[database.connections]
"primary.server" = { host = "db1.example.com", port = 5432 }
TOML;
        $doc = Toml::parse($toml);

        // Act
        $host = $doc->get('database.connections."primary.server".host');
        $port = $doc->get('database.connections."primary.server".port');

        // Assert
        self::assertSame('db1.example.com', $host);
        self::assertSame(5432, $port);
    }

    public function testQuotedKeyDoesNotSplitOnInternalDots(): void
    {
        // Arrange
        $toml = <<<'TOML'
[section]
"a.b.c" = "should not split"
TOML;
        $doc = Toml::parse($toml);

        // Act
        $direct = $doc->get('section."a.b.c"');
        $wrongPath = $doc->get('section.a.b.c');

        // Assert
        self::assertSame('should not split', $direct);
        self::assertNull($wrongPath, 'Path without quotes should not match quoted key');
    }
}
