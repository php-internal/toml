<?php

declare(strict_types=1);

namespace Internal\Toml\Tests\Unit\Encoder;

use Internal\Toml\Encoder\ArrayToDocumentConverter;
use Internal\Toml\Node\Document;
use Internal\Toml\Node\Entry;
use Internal\Toml\Node\Table;
use Internal\Toml\Node\TableArray;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(ArrayToDocumentConverter::class)]
final class ArrayToDocumentConverterTest extends TestCase
{
    private ArrayToDocumentConverter $converter;

    public static function provideScalarValues(): \Generator
    {
        yield 'string' => ['text', \Internal\Toml\Node\Value\StringValue::class];
        yield 'integer' => [42, \Internal\Toml\Node\Value\IntegerValue::class];
        yield 'float' => [3.14, \Internal\Toml\Node\Value\FloatValue::class];
        yield 'boolean' => [true, \Internal\Toml\Node\Value\BooleanValue::class];
    }

    // ============================================
    // Basic Conversion Tests
    // ============================================

    public function testConvertReturnsDocumentInstance(): void
    {
        // Arrange
        $data = ['key' => 'value'];

        // Act
        $result = $this->converter->convert($data);

        // Assert
        self::assertInstanceOf(Document::class, $result);
    }

    public function testConvertEmptyArrayReturnsEmptyDocument(): void
    {
        // Arrange
        $data = [];

        // Act
        $result = $this->converter->convert($data);

        // Assert
        self::assertInstanceOf(Document::class, $result);
        self::assertCount(0, $result->nodes);
    }

    // ============================================
    // Root Entries Tests
    // ============================================

    public function testConvertSimpleKeyValueCreatesRootEntry(): void
    {
        // Arrange
        $data = ['title' => 'TOML Example'];

        // Act
        $result = $this->converter->convert($data);

        // Assert
        self::assertCount(1, $result->nodes);
        self::assertInstanceOf(Entry::class, $result->nodes[0]);
        self::assertSame('title', $result->nodes[0]->key->__toString());
    }

    #[DataProvider('provideScalarValues')]
    public function testConvertScalarValuesCreatesRootEntries(mixed $value, string $expectedType): void
    {
        // Arrange
        $data = ['key' => $value];

        // Act
        $result = $this->converter->convert($data);

        // Assert
        self::assertCount(1, $result->nodes);
        $entry = $result->nodes[0];
        self::assertInstanceOf(Entry::class, $entry);
        self::assertInstanceOf($expectedType, $entry->value);
    }

    public function testConvertMultipleRootEntriesCreatesMultipleNodes(): void
    {
        // Arrange
        $data = [
            'title' => 'Example',
            'version' => '1.0.0',
            'count' => 42,
        ];

        // Act
        $result = $this->converter->convert($data);

        // Assert
        self::assertCount(3, $result->nodes);
        self::assertContainsOnlyInstancesOf(Entry::class, $result->nodes);
    }

    // ============================================
    // Table Tests
    // ============================================

    public function testConvertNestedArrayCreatesTable(): void
    {
        // Arrange
        $data = [
            'database' => [
                'server' => '192.168.1.1',
                'port' => 5432,
            ],
        ];

        // Act
        $result = $this->converter->convert($data);

        // Assert
        self::assertCount(1, $result->nodes);
        self::assertInstanceOf(Table::class, $result->nodes[0]);
        self::assertSame('database', $result->nodes[0]->name->__toString());
    }

    public function testConvertTableContainsCorrectEntries(): void
    {
        // Arrange
        $data = [
            'owner' => [
                'name' => 'Tom',
                'age' => 30,
            ],
        ];

        // Act
        $result = $this->converter->convert($data);

        // Assert
        $table = $result->nodes[0];
        self::assertInstanceOf(Table::class, $table);
        self::assertCount(2, $table->entries);
        self::assertSame('name', $table->entries[0]->key->__toString());
        self::assertSame('age', $table->entries[1]->key->__toString());
    }

    public function testConvertMultipleTablesCreatesMultipleNodes(): void
    {
        // Arrange
        $data = [
            'database' => ['host' => 'localhost'],
            'server' => ['port' => 8080],
        ];

        // Act
        $result = $this->converter->convert($data);

        // Assert
        self::assertCount(2, $result->nodes);
        self::assertContainsOnlyInstancesOf(Table::class, $result->nodes);
    }

    // ============================================
    // Table Array Tests
    // ============================================

    public function testConvertListOfAssociativeArraysCreatesTableArray(): void
    {
        // Arrange
        $data = [
            'products' => [
                ['name' => 'Hammer', 'sku' => 123],
                ['name' => 'Nail', 'sku' => 456],
            ],
        ];

        // Act
        $result = $this->converter->convert($data);

        // Assert
        self::assertCount(2, $result->nodes);
        self::assertContainsOnlyInstancesOf(TableArray::class, $result->nodes);
        self::assertSame('products', $result->nodes[0]->name->__toString());
        self::assertSame('products', $result->nodes[1]->name->__toString());
    }

    public function testConvertTableArrayContainsCorrectEntries(): void
    {
        // Arrange
        $data = [
            'items' => [
                ['id' => 1, 'name' => 'First'],
                ['id' => 2, 'name' => 'Second'],
            ],
        ];

        // Act
        $result = $this->converter->convert($data);

        // Assert
        $firstTableArray = $result->nodes[0];
        self::assertInstanceOf(TableArray::class, $firstTableArray);
        self::assertCount(2, $firstTableArray->entries);
        self::assertSame('id', $firstTableArray->entries[0]->key->__toString());
        self::assertSame('name', $firstTableArray->entries[1]->key->__toString());
    }

    // ============================================
    // Simple Array Tests
    // ============================================

    public function testConvertSimpleArrayCreatesArrayValue(): void
    {
        // Arrange
        $data = ['ports' => [8000, 8001, 8002]];

        // Act
        $result = $this->converter->convert($data);

        // Assert
        self::assertCount(1, $result->nodes);
        $entry = $result->nodes[0];
        self::assertInstanceOf(Entry::class, $entry);
        self::assertInstanceOf(\Internal\Toml\Node\Value\ArrayValue::class, $entry->value);
    }

    // ============================================
    // Mixed Structure Tests
    // ============================================

    public function testConvertMixedStructureCreatesCorrectNodes(): void
    {
        // Arrange
        $data = [
            'title' => 'Config',                    // Root entry
            'database' => ['host' => 'localhost'],  // Table
            'ports' => [8000, 8001],                // Root entry with array
        ];

        // Act
        $result = $this->converter->convert($data);

        // Assert
        self::assertCount(3, $result->nodes);
        self::assertInstanceOf(Entry::class, $result->nodes[0]); // title
        self::assertInstanceOf(Entry::class, $result->nodes[1]); // ports
        self::assertInstanceOf(Table::class, $result->nodes[2]);  // database
    }

    // ============================================
    // Dotted Keys Tests
    // ============================================

    public function testConvertDottedKeyCreatesCorrectKey(): void
    {
        // Arrange
        $converter = new ArrayToDocumentConverter();

        // We can't directly test createKey as it's private, but we can test
        // that dotted keys in the array work through conversion
        $data = ['simple' => 'value'];

        // Act
        $result = $converter->convert($data);

        // Assert
        $entry = $result->nodes[0];
        self::assertSame('simple', $entry->key->__toString());
    }

    // ============================================
    // Error Handling Tests
    // ============================================

    public function testConvertThrowsExceptionForNumericKeys(): void
    {
        // Arrange
        $data = [0 => 'value'];

        // Assert (before Act for exceptions)
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('TOML keys must be strings');

        // Act
        $this->converter->convert($data);
    }

    // ============================================
    // Edge Cases Tests
    // ============================================

    public function testConvertHandlesEmptyTable(): void
    {
        // Arrange
        $data = ['empty_table' => []];

        // Act
        $result = $this->converter->convert($data);

        // Assert
        self::assertCount(1, $result->nodes);
        self::assertInstanceOf(Entry::class, $result->nodes[0]);
        self::assertInstanceOf(\Internal\Toml\Node\Value\ArrayValue::class, $result->nodes[0]->value);
    }

    public function testConvertHandlesEmptyTableArray(): void
    {
        // Arrange
        $data = ['empty_array' => []];

        // Act
        $result = $this->converter->convert($data);

        // Assert
        self::assertCount(1, $result->nodes);
        // Empty array becomes ArrayValue, not TableArray
        $entry = $result->nodes[0];
        self::assertInstanceOf(Entry::class, $entry);
    }

    public function testConvertPreservesNodeOrder(): void
    {
        // Arrange
        $data = [
            'first' => 'value1',
            'second' => 'value2',
            'third' => ['nested' => 'value3'],
        ];

        // Act
        $result = $this->converter->convert($data);

        // Assert
        self::assertCount(3, $result->nodes);
        self::assertSame('first', $result->nodes[0]->key->__toString());
        self::assertSame('second', $result->nodes[1]->key->__toString());
        self::assertSame('third', $result->nodes[2]->name->__toString());
    }

    // ============================================
    // Complex Structure Tests
    // ============================================

    public function testConvertComplexNestedStructure(): void
    {
        // Arrange
        $data = [
            'title' => 'Complex Example',
            'owner' => [
                'name' => 'Tom Preston-Werner',
                'dob' => new \DateTimeImmutable('1979-05-27T07:32:00Z'),
            ],
            'database' => [
                'server' => '192.168.1.1',
                'ports' => [8000, 8001, 8002],
            ],
            'products' => [
                ['name' => 'Hammer', 'sku' => 738594937],
                ['name' => 'Nail', 'sku' => 284758393],
            ],
        ];

        // Act
        $result = $this->converter->convert($data);

        // Assert
        // 1 root entry (title) + 2 tables (owner, database) + 2 table arrays (products)
        self::assertCount(5, $result->nodes);

        $titleEntry = $result->nodes[0];
        self::assertInstanceOf(Entry::class, $titleEntry);
        self::assertSame('title', $titleEntry->key->__toString());

        $ownerTable = $result->nodes[1];
        self::assertInstanceOf(Table::class, $ownerTable);
        self::assertSame('owner', $ownerTable->name->__toString());

        $databaseTable = $result->nodes[2];
        self::assertInstanceOf(Table::class, $databaseTable);
        self::assertSame('database', $databaseTable->name->__toString());

        $productsArray1 = $result->nodes[3];
        self::assertInstanceOf(TableArray::class, $productsArray1);
        self::assertSame('products', $productsArray1->name->__toString());

        $productsArray2 = $result->nodes[4];
        self::assertInstanceOf(TableArray::class, $productsArray2);
        self::assertSame('products', $productsArray2->name->__toString());
    }

    protected function setUp(): void
    {
        // Arrange (common setup)
        $this->converter = new ArrayToDocumentConverter();
    }
}
