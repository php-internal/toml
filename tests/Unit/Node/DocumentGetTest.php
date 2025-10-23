<?php

declare(strict_types=1);

namespace Internal\Toml\Tests\Unit\Node;

use Internal\Toml\Toml;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(\Internal\Toml\Node\Document::class)]
#[CoversClass(\Internal\Toml\Node\ValueFinder::class)]
final class DocumentGetTest extends TestCase
{
    public static function provideVariousValueTypes(): \Generator
    {
        yield 'string value' => [
            'key = "value"',
            'key',
            'value',
        ];

        yield 'integer value' => [
            'port = 8080',
            'port',
            8080,
        ];

        yield 'float value' => [
            'pi = 3.14',
            'pi',
            3.14,
        ];

        yield 'boolean true' => [
            'enabled = true',
            'enabled',
            true,
        ];

        yield 'boolean false' => [
            'disabled = false',
            'disabled',
            false,
        ];

        yield 'hex integer' => [
            'hex = 0xDEADBEEF',
            'hex',
            0xDEADBEEF,
        ];

        yield 'octal integer' => [
            'octal = 0o755',
            'octal',
            0o755,
        ];

        yield 'binary integer' => [
            'binary = 0b11010110',
            'binary',
            0b11010110,
        ];
    }

    public static function provideComplexNavigationScenarios(): \Generator
    {
        yield 'nested array in table' => [
            <<<'TOML'
                [server]
                ports = [8080, 8081, 8082]
                TOML,
            'server.ports.1',
            8081,
        ];

        yield 'inline table in array' => [
            'items = [{name = "foo"}, {name = "bar"}]',
            'items.0.name',
            'foo',
        ];

        yield 'deeply nested structure' => [
            <<<'TOML'
                [a.b.c.d]
                value = "deep"
                TOML,
            'a.b.c.d.value',
            'deep',
        ];

        yield 'table array with nested values' => [
            <<<'TOML'
                [[products]]
                name = "Hammer"
                specs = { weight = 500, material = "steel" }
                TOML,
            'products.0.specs.material',
            'steel',
        ];
    }

    public function testGetSimpleKey(): void
    {
        // Arrange
        $toml = 'name = "myapp"';
        $doc = Toml::parse($toml);

        // Act
        $result = $doc->get('name');

        // Assert
        self::assertSame('myapp', $result);
    }

    public function testGetDottedKey(): void
    {
        // Arrange
        $toml = <<<'TOML'
[database]
host = "localhost"
TOML;
        $doc = Toml::parse($toml);

        // Act
        $result = $doc->get('database.host');

        // Assert
        self::assertSame('localhost', $result);
    }

    public function testGetDeepNestedKey(): void
    {
        // Arrange
        $toml = <<<'TOML'
            [package.dependencies]
            php = ">=8.1"

            [package.dependencies.dev]
            phpunit = "^10.0"
            TOML;
        $doc = Toml::parse($toml);

        // Act
        $phpVersion = $doc->get('package.dependencies.php');
        $phpunitVersion = $doc->get('package.dependencies.dev.phpunit');

        // Assert
        self::assertSame('>=8.1', $phpVersion);
        self::assertSame('^10.0', $phpunitVersion);
    }

    public function testGetArrayValue(): void
    {
        // Arrange
        $toml = 'servers = ["alpha", "beta", "gamma"]';
        $doc = Toml::parse($toml);

        // Act
        $result = $doc->get('servers');

        // Assert
        self::assertSame(['alpha', 'beta', 'gamma'], $result);
    }

    public function testGetArrayIndex(): void
    {
        // Arrange
        $toml = 'servers = ["alpha", "beta", "gamma"]';
        $doc = Toml::parse($toml);

        // Act
        $first = $doc->get('servers.0');
        $second = $doc->get('servers.1');
        $third = $doc->get('servers.2');

        // Assert
        self::assertSame('alpha', $first);
        self::assertSame('beta', $second);
        self::assertSame('gamma', $third);
    }

    public function testGetTableArrayWithIndex(): void
    {
        // Arrange
        $toml = <<<'TOML'
            [[products]]
            name = "Hammer"
            sku = 738594937

            [[products]]
            name = "Nail"
            sku = 284758393
            color = "gray"
            TOML;
        $doc = Toml::parse($toml);

        // Act
        $hammerName = $doc->get('products.0.name');
        $hammerSku = $doc->get('products.0.sku');
        $nailName = $doc->get('products.1.name');
        $nailColor = $doc->get('products.1.color');

        // Assert
        self::assertSame('Hammer', $hammerName);
        self::assertSame(738594937, $hammerSku);
        self::assertSame('Nail', $nailName);
        self::assertSame('gray', $nailColor);
    }

    public function testGetInlineTable(): void
    {
        // Arrange
        $toml = 'server = { host = "localhost", port = 8080 }';
        $doc = Toml::parse($toml);

        // Act
        $host = $doc->get('server.host');
        $port = $doc->get('server.port');

        // Assert
        self::assertSame('localhost', $host);
        self::assertSame(8080, $port);
    }

    public function testGetNestedInlineTable(): void
    {
        // Arrange
        $toml = <<<'TOML'
            [database]
            primary = { host = "db1.example.com", port = 5432 }
            TOML;
        $doc = Toml::parse($toml);

        // Act
        $host = $doc->get('database.primary.host');
        $port = $doc->get('database.primary.port');

        // Assert
        self::assertSame('db1.example.com', $host);
        self::assertSame(5432, $port);
    }

    public function testGetWholeTable(): void
    {
        // Arrange
        $toml = <<<'TOML'
            [database]
            host = "localhost"
            port = 5432
            TOML;
        $doc = Toml::parse($toml);

        // Act
        $result = $doc->get('database');

        // Assert
        self::assertIsArray($result);
        self::assertSame('localhost', $result['host']);
        self::assertSame(5432, $result['port']);
    }

    public function testGetWithDefault(): void
    {
        // Arrange
        $toml = 'name = "myapp"';
        $doc = Toml::parse($toml);

        // Act
        $existing = $doc->get('name', 'default');
        $missing = $doc->get('nonexistent', 'default');

        // Assert
        self::assertSame('myapp', $existing);
        self::assertSame('default', $missing);
    }

    public function testGetNonExistentKeyReturnsNull(): void
    {
        // Arrange
        $toml = 'name = "myapp"';
        $doc = Toml::parse($toml);

        // Act
        $result = $doc->get('nonexistent');

        // Assert
        self::assertNull($result);
    }

    public function testGetInvalidArrayIndexReturnsNull(): void
    {
        // Arrange
        $toml = 'items = [1, 2, 3]';
        $doc = Toml::parse($toml);

        // Act
        $result = $doc->get('items.10');

        // Assert
        self::assertNull($result);
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

    public function testGetMixedQuotedAndRegularKeys(): void
    {
        // Arrange
        $toml = <<<'TOML'
            [section]
            "key.with.dots" = "value1"
            regular = "value2"
            TOML;
        $doc = Toml::parse($toml);

        // Act
        $quoted = $doc->get('section."key.with.dots"');
        $regular = $doc->get('section.regular');

        // Assert
        self::assertSame('value1', $quoted);
        self::assertSame('value2', $regular);
    }

    public function testGetEmptyPathThrowsException(): void
    {
        // Arrange
        $toml = 'name = "myapp"';
        $doc = Toml::parse($toml);

        // Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Key path cannot be empty');

        // Act
        $doc->get('');
    }

    public function testHasExistingKey(): void
    {
        // Arrange
        $toml = <<<'TOML'
            name = "myapp"

            [database]
            host = "localhost"
            TOML;
        $doc = Toml::parse($toml);

        // Act & Assert
        self::assertTrue($doc->has('name'));
        self::assertTrue($doc->has('database'));
        self::assertTrue($doc->has('database.host'));
    }

    public function testHasNonExistentKey(): void
    {
        // Arrange
        $toml = 'name = "myapp"';
        $doc = Toml::parse($toml);

        // Act & Assert
        self::assertFalse($doc->has('nonexistent'));
        self::assertFalse($doc->has('database.host'));
    }

    public function testHasArrayIndex(): void
    {
        // Arrange
        $toml = 'items = [1, 2, 3]';
        $doc = Toml::parse($toml);

        // Act & Assert
        self::assertTrue($doc->has('items.0'));
        self::assertTrue($doc->has('items.2'));
        self::assertFalse($doc->has('items.10'));
    }

    public function testHasEmptyPathReturnsFalse(): void
    {
        // Arrange
        $toml = 'name = "myapp"';
        $doc = Toml::parse($toml);

        // Act
        $result = $doc->has('');

        // Assert
        self::assertFalse($result);
    }

    #[DataProvider('provideVariousValueTypes')]
    public function testGetVariousValueTypes(string $toml, string $path, mixed $expected): void
    {
        // Arrange
        $doc = Toml::parse($toml);

        // Act
        $result = $doc->get($path);

        // Assert
        self::assertSame($expected, $result);
    }

    #[DataProvider('provideComplexNavigationScenarios')]
    public function testComplexNavigation(string $toml, string $path, mixed $expected): void
    {
        // Arrange
        $doc = Toml::parse($toml);

        // Act
        $result = $doc->get($path);

        // Assert
        self::assertSame($expected, $result);
    }

    public function testGetDateTimeValue(): void
    {
        // Arrange
        $toml = 'created_at = 2023-01-15T10:30:00Z';
        $doc = Toml::parse($toml);

        // Act
        $result = $doc->get('created_at');

        // Assert
        self::assertInstanceOf(\DateTimeImmutable::class, $result);
        self::assertSame('2023-01-15 10:30:00', $result->format('Y-m-d H:i:s'));
    }

    public function testGetMultilineString(): void
    {
        // Arrange
        $toml = <<<'TOML'
            description = """
            Line 1
            Line 2
            Line 3"""
            TOML;
        $doc = Toml::parse($toml);

        // Act
        $result = $doc->get('description');

        // Assert
        self::assertSame("Line 1\nLine 2\nLine 3", $result);
    }

    public function testGetSpecialFloatValues(): void
    {
        // Arrange
        $toml = <<<'TOML'
            pos_inf = inf
            neg_inf = -inf
            not_a_number = nan
            TOML;
        $doc = Toml::parse($toml);

        // Act
        $posInf = $doc->get('pos_inf');
        $negInf = $doc->get('neg_inf');
        $nan = $doc->get('not_a_number');

        // Assert
        self::assertIsFloat($posInf);
        self::assertTrue(\is_infinite($posInf));
        self::assertTrue($posInf > 0);

        self::assertIsFloat($negInf);
        self::assertTrue(\is_infinite($negInf));
        self::assertTrue($negInf < 0);

        self::assertIsFloat($nan);
        self::assertTrue(\is_nan($nan));
    }

    public function testGetFromDottedKeyInRootEntry(): void
    {
        // Arrange
        $toml = 'a.b.c = "nested"';
        $doc = Toml::parse($toml);

        // Act
        $result = $doc->get('a.b.c');

        // Assert
        self::assertSame('nested', $result);
    }

    public function testCannotNavigateThroughScalarValue(): void
    {
        // Arrange
        $toml = 'name = "value"';
        $doc = Toml::parse($toml);

        // Act
        $result = $doc->get('name.subkey');

        // Assert
        self::assertNull($result);
    }

    public function testGetEmptyArray(): void
    {
        // Arrange
        $toml = 'empty = []';
        $doc = Toml::parse($toml);

        // Act
        $result = $doc->get('empty');

        // Assert
        self::assertSame([], $result);
    }

    public function testGetEmptyInlineTable(): void
    {
        // Arrange
        $toml = 'empty = {}';
        $doc = Toml::parse($toml);

        // Act
        $result = $doc->get('empty');

        // Assert
        self::assertSame([], $result);
    }
}
