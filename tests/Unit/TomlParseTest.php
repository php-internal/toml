<?php

declare(strict_types=1);

namespace Internal\Toml\Tests\Unit;

use Internal\Toml\Exception\DuplicateKeyException;
use Internal\Toml\Exception\SyntaxException;
use Internal\Toml\Node\Document;
use Internal\Toml\Node\Entry;
use Internal\Toml\Node\Key;
use Internal\Toml\Node\Table;
use Internal\Toml\Node\TableArray;
use Internal\Toml\Node\Value\ArrayValue;
use Internal\Toml\Node\Value\BooleanValue;
use Internal\Toml\Node\Value\DateTimeType;
use Internal\Toml\Node\Value\DateTimeValue;
use Internal\Toml\Node\Value\FloatValue;
use Internal\Toml\Node\Value\InlineTableValue;
use Internal\Toml\Node\Value\IntegerFormat;
use Internal\Toml\Node\Value\IntegerValue;
use Internal\Toml\Node\Value\LocalTimeValue;
use Internal\Toml\Node\Value\StringType;
use Internal\Toml\Node\Value\StringValue;
use Internal\Toml\Toml;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[CoversClass(Toml::class)]
final class TomlParseTest extends TestCase
{
    public static function provideIntegerFormats(): \Generator
    {
        yield 'decimal integer' => ['int = 42', 42, IntegerFormat::Decimal, '42'];
        yield 'decimal with underscores' => ['int = 1_000', 1000, IntegerFormat::Decimal, '1_000'];
        yield 'hex integer' => ['int = 0xDEAD', 0xDEAD, IntegerFormat::Hex, '0xDEAD'];
        yield 'octal integer' => ['int = 0o755', 0755, IntegerFormat::Octal, '0o755'];
        yield 'binary integer' => ['int = 0b1101', 0b1101, IntegerFormat::Binary, '0b1101'];
    }
    // ============================================
    // Document Structure Tests
    // ============================================

    public function testParseReturnsDocumentInstance(): void
    {
        // Arrange
        $toml = 'key = "value"';

        // Act
        $result = Toml::parse($toml);

        // Assert
        self::assertInstanceOf(Document::class, $result);
    }

    public function testParseEmptyStringReturnsDocumentWithNoNodes(): void
    {
        // Arrange
        $toml = '';

        // Act
        $result = Toml::parse($toml);

        // Assert
        self::assertInstanceOf(Document::class, $result);
        self::assertCount(0, $result->nodes);
    }

    public function testParseOnlyCommentsReturnsDocumentWithCommentEntries(): void
    {
        // Arrange
        $toml = <<<'TOML'
# First comment
# Second comment
TOML;

        // Act
        $result = Toml::parse($toml);

        // Assert
        self::assertInstanceOf(Document::class, $result);
        self::assertCount(2, $result->nodes);
        self::assertContainsOnlyInstancesOf(Entry::class, $result->nodes);

        $entries = $result->nodes;
        self::assertTrue($entries[0]->isComment());
        self::assertSame(' First comment', $entries[0]->comment);
        self::assertTrue($entries[1]->isComment());
        self::assertSame(' Second comment', $entries[1]->comment);
    }

    // ============================================
    // Entry Tests
    // ============================================

    public function testParseSimpleKeyValueReturnsEntry(): void
    {
        // Arrange
        $toml = 'key = "value"';

        // Act
        $result = Toml::parse($toml);

        // Assert
        self::assertCount(1, $result->nodes);
        $entry = $result->nodes[0];
        self::assertInstanceOf(Entry::class, $entry);
        self::assertTrue($entry->isKeyValue());
        self::assertNotNull($entry->key);
        self::assertNotNull($entry->value);
        self::assertNull($entry->comment);
    }

    public function testParseKeyValueWithInlineCommentCapturesBoth(): void
    {
        // Arrange
        $toml = 'key = "value" # inline comment';

        // Act
        $result = Toml::parse($toml);

        // Assert
        self::assertCount(1, $result->nodes);
        $entry = $result->nodes[0];
        self::assertInstanceOf(Entry::class, $entry);
        self::assertTrue($entry->isKeyValue());
        self::assertTrue($entry->hasInlineComment());
        self::assertSame(' inline comment', $entry->comment);
    }

    // ============================================
    // Key Tests
    // ============================================

    public function testParseBareKeyCreatesKeyWithCorrectSegments(): void
    {
        // Arrange
        $toml = 'simple_key = "value"';

        // Act
        $result = Toml::parse($toml);

        // Assert
        $entry = $result->nodes[0];
        self::assertInstanceOf(Key::class, $entry->key);
        self::assertSame(['simple_key'], $entry->key->segments);
        self::assertTrue($entry->key->isSimple());
        self::assertFalse($entry->key->isDotted());
        self::assertSame('simple_key', (string) $entry->key);
    }

    public function testParseQuotedKeyCreatesKeyWithCorrectSegments(): void
    {
        // Arrange
        $toml = '"quoted key" = "value"';

        // Act
        $result = Toml::parse($toml);

        // Assert
        $entry = $result->nodes[0];
        self::assertInstanceOf(Key::class, $entry->key);
        self::assertSame(['quoted key'], $entry->key->segments);
        self::assertSame('"quoted key"', (string) $entry->key);
    }

    public function testParseDottedKeyCreatesKeyWithMultipleSegments(): void
    {
        // Arrange
        $toml = 'parent.child = "value"';

        // Act
        $result = Toml::parse($toml);

        // Assert
        $entry = $result->nodes[0];
        self::assertInstanceOf(Key::class, $entry->key);
        self::assertSame(['parent', 'child'], $entry->key->segments);
        self::assertFalse($entry->key->isSimple());
        self::assertTrue($entry->key->isDotted());
        self::assertSame('parent', $entry->key->getFirstSegment());
        self::assertSame('child', $entry->key->getLastSegment());
        self::assertSame('parent.child', (string) $entry->key);
    }

    public function testKeyToStringAutoQuotesWhenNeeded(): void
    {
        // Arrange & Act - bare key doesn't need quoting
        $bareKey = new Key(['simple_key'], new \Internal\Toml\Node\Position(1, 1, 0));

        // Assert
        self::assertSame('simple_key', (string) $bareKey);

        // Arrange & Act - key with spaces needs quoting
        $quotedKey = new Key(['quoted key'], new \Internal\Toml\Node\Position(1, 1, 0));

        // Assert
        self::assertSame('"quoted key"', (string) $quotedKey);

        // Arrange & Act - dotted key with mixed types
        $dottedKey = new Key(['bare', 'has space', 'bare-dash'], new \Internal\Toml\Node\Position(1, 1, 0));

        // Assert
        self::assertSame('bare."has space".bare-dash', (string) $dottedKey);

        // Arrange & Act - empty key needs quoting
        $emptyKey = new Key([''], new \Internal\Toml\Node\Position(1, 1, 0));

        // Assert
        self::assertSame('""', (string) $emptyKey);
    }

    // ============================================
    // String Value Tests
    // ============================================

    public function testParseBasicStringCreatesStringValueWithBasicType(): void
    {
        // Arrange
        $toml = 'str = "hello world"';

        // Act
        $result = Toml::parse($toml);

        // Assert
        $entry = $result->nodes[0];
        $value = $entry->value;
        self::assertInstanceOf(StringValue::class, $value);
        self::assertSame('hello world', $value->value);
        self::assertSame(StringType::Basic, $value->type);
    }

    public function testParseLiteralStringCreatesStringValueWithLiteralType(): void
    {
        // Arrange
        $toml = "str = 'hello world'";

        // Act
        $result = Toml::parse($toml);

        // Assert
        $entry = $result->nodes[0];
        $value = $entry->value;
        self::assertInstanceOf(StringValue::class, $value);
        self::assertSame('hello world', $value->value);
        self::assertSame(StringType::Literal, $value->type);
    }

    public function testParseMultilineBasicStringCreatesCorrectType(): void
    {
        // Arrange
        $toml = <<<'TOML'
str = """
line one
line two"""
TOML;

        // Act
        $result = Toml::parse($toml);

        // Assert
        $entry = $result->nodes[0];
        $value = $entry->value;
        self::assertInstanceOf(StringValue::class, $value);
        self::assertSame("line one\nline two", $value->value);
        self::assertSame(StringType::MultilineBasic, $value->type);
    }

    public function testParseMultilineLiteralStringCreatesCorrectType(): void
    {
        // Arrange
        $toml = <<<'TOML'
str = '''
line one\n
line two'''
TOML;

        // Act
        $result = Toml::parse($toml);

        // Assert
        $entry = $result->nodes[0];
        $value = $entry->value;
        self::assertInstanceOf(StringValue::class, $value);
        self::assertSame("line one\\n\nline two", $value->value);
        self::assertSame(StringType::MultilineLiteral, $value->type);
    }

    // ============================================
    // Integer Value Tests
    // ============================================

    #[DataProvider('provideIntegerFormats')]
    public function testParseIntegerWithVariousFormats(string $toml, int $expectedValue, IntegerFormat $expectedFormat, string $expectedRaw): void
    {
        // Arrange & Act
        $result = Toml::parse($toml);

        // Assert
        $entry = $result->nodes[0];
        $value = $entry->value;
        self::assertInstanceOf(IntegerValue::class, $value);
        self::assertSame($expectedValue, $value->value);
        self::assertSame($expectedFormat, $value->format);
        self::assertSame($expectedRaw, $value->raw);
    }

    // ============================================
    // Float Value Tests
    // ============================================

    public function testParseFloatCreatesFloatValue(): void
    {
        // Arrange
        $toml = 'flt = 3.14';

        // Act
        $result = Toml::parse($toml);

        // Assert
        $entry = $result->nodes[0];
        $value = $entry->value;
        self::assertInstanceOf(FloatValue::class, $value);
        self::assertSame(3.14, $value->value);
        self::assertSame('3.14', $value->raw);
        self::assertFalse($value->isInfinity());
        self::assertFalse($value->isNaN());
    }

    public function testParseInfinityCreatesFloatValueWithInfinity(): void
    {
        // Arrange
        $toml = 'flt = inf';

        // Act
        $result = Toml::parse($toml);

        // Assert
        $entry = $result->nodes[0];
        $value = $entry->value;
        self::assertInstanceOf(FloatValue::class, $value);
        self::assertTrue($value->isInfinity());
        self::assertSame(INF, $value->value);
    }

    public function testParseNaNCreatesFloatValueWithNaN(): void
    {
        // Arrange
        $toml = 'flt = nan';

        // Act
        $result = Toml::parse($toml);

        // Assert
        $entry = $result->nodes[0];
        $value = $entry->value;
        self::assertInstanceOf(FloatValue::class, $value);
        self::assertTrue($value->isNaN());
    }

    // ============================================
    // Boolean Value Tests
    // ============================================

    public function testParseTrueCreatesBooleanValue(): void
    {
        // Arrange
        $toml = 'bool = true';

        // Act
        $result = Toml::parse($toml);

        // Assert
        $entry = $result->nodes[0];
        $value = $entry->value;
        self::assertInstanceOf(BooleanValue::class, $value);
        self::assertTrue($value->value);
    }

    public function testParseFalseCreatesBooleanValue(): void
    {
        // Arrange
        $toml = 'bool = false';

        // Act
        $result = Toml::parse($toml);

        // Assert
        $entry = $result->nodes[0];
        $value = $entry->value;
        self::assertInstanceOf(BooleanValue::class, $value);
        self::assertFalse($value->value);
    }

    // ============================================
    // DateTime Value Tests
    // ============================================

    public function testParseOffsetDateTimeCreatesDateTimeValueWithCorrectType(): void
    {
        // Arrange
        $toml = 'dt = 1979-05-27T07:32:00Z';

        // Act
        $result = Toml::parse($toml);

        // Assert
        $entry = $result->nodes[0];
        $value = $entry->value;
        self::assertInstanceOf(DateTimeValue::class, $value);
        self::assertSame(DateTimeType::OffsetDatetime, $value->type);
        self::assertInstanceOf(\DateTimeImmutable::class, $value->value);
        self::assertSame('1979-05-27T07:32:00Z', $value->raw);
    }

    public function testParseOffsetDateTimeWithNumericOffset(): void
    {
        // Arrange
        $toml = 'dt = 1979-05-27T07:32:00+05:30';

        // Act
        $result = Toml::parse($toml);

        // Assert
        $entry = $result->nodes[0];
        $value = $entry->value;
        self::assertInstanceOf(DateTimeValue::class, $value);
        self::assertSame(DateTimeType::OffsetDatetime, $value->type);
    }

    public function testParseOffsetDateTimeWithNegativeOffset(): void
    {
        // Arrange
        $toml = 'dt = 1987-07-05T17:45:56-05:00';

        // Act
        $result = Toml::parse($toml);

        // Assert
        $entry = $result->nodes[0];
        $value = $entry->value;
        self::assertInstanceOf(DateTimeValue::class, $value);
        self::assertSame(DateTimeType::OffsetDatetime, $value->type);
    }

    public function testParseLocalDateTimeCreatesDateTimeValueWithCorrectType(): void
    {
        // Arrange
        $toml = 'dt = 1979-05-27T07:32:00';

        // Act
        $result = Toml::parse($toml);

        // Assert
        $entry = $result->nodes[0];
        $value = $entry->value;
        self::assertInstanceOf(DateTimeValue::class, $value);
        self::assertSame(DateTimeType::LocalDatetime, $value->type);
    }

    public function testParseLocalDateCreatesDateTimeValueWithCorrectType(): void
    {
        // Arrange
        $toml = 'dt = 1979-05-27';

        // Act
        $result = Toml::parse($toml);

        // Assert
        $entry = $result->nodes[0];
        $value = $entry->value;
        self::assertInstanceOf(DateTimeValue::class, $value);
        self::assertSame(DateTimeType::LocalDate, $value->type);
        self::assertSame('1979-05-27', $value->raw);
    }

    public function testParseLocalTimeCreatesLocalTimeValue(): void
    {
        // Arrange
        $toml = 'time = 07:32:00';

        // Act
        $result = Toml::parse($toml);

        // Assert
        $entry = $result->nodes[0];
        $value = $entry->value;
        self::assertInstanceOf(LocalTimeValue::class, $value);
        self::assertSame('07:32:00', $value->value);
    }

    // ============================================
    // Array Value Tests
    // ============================================

    public function testParseArrayCreatesArrayValue(): void
    {
        // Arrange
        $toml = 'arr = [1, 2, 3]';

        // Act
        $result = Toml::parse($toml);

        // Assert
        $entry = $result->nodes[0];
        $value = $entry->value;
        self::assertInstanceOf(ArrayValue::class, $value);
        self::assertFalse($value->isEmpty());
        self::assertSame(3, $value->count());
        self::assertContainsOnlyInstancesOf(IntegerValue::class, $value->elements);
    }

    public function testParseEmptyArrayCreatesEmptyArrayValue(): void
    {
        // Arrange
        $toml = 'arr = []';

        // Act
        $result = Toml::parse($toml);

        // Assert
        $entry = $result->nodes[0];
        $value = $entry->value;
        self::assertInstanceOf(ArrayValue::class, $value);
        self::assertTrue($value->isEmpty());
        self::assertSame(0, $value->count());
    }

    public function testParseNestedArrayCreatesNestedArrayValue(): void
    {
        // Arrange
        $toml = 'arr = [[1, 2], [3, 4]]';

        // Act
        $result = Toml::parse($toml);

        // Assert
        $entry = $result->nodes[0];
        $value = $entry->value;
        self::assertInstanceOf(ArrayValue::class, $value);
        self::assertSame(2, $value->count());
        self::assertContainsOnlyInstancesOf(ArrayValue::class, $value->elements);
    }

    // ============================================
    // Inline Table Tests
    // ============================================

    public function testParseInlineTableCreatesInlineTableValue(): void
    {
        // Arrange
        $toml = 'table = {key = "value", num = 42}';

        // Act
        $result = Toml::parse($toml);

        // Assert
        $entry = $result->nodes[0];
        $value = $entry->value;
        self::assertInstanceOf(InlineTableValue::class, $value);
        self::assertTrue($value->has('key'));
        self::assertTrue($value->has('num'));
        self::assertInstanceOf(StringValue::class, $value->get('key'));
        self::assertInstanceOf(IntegerValue::class, $value->get('num'));
    }

    public function testParseEmptyInlineTableCreatesEmptyInlineTableValue(): void
    {
        // Arrange
        $toml = 'table = {}';

        // Act
        $result = Toml::parse($toml);

        // Assert
        $entry = $result->nodes[0];
        $value = $entry->value;
        self::assertInstanceOf(InlineTableValue::class, $value);
        self::assertSame([], $value->pairs);
    }

    // ============================================
    // Table Tests
    // ============================================

    public function testParseTableCreatesTableNode(): void
    {
        // Arrange
        $toml = <<<'TOML'
[table]
key = "value"
TOML;

        // Act
        $result = Toml::parse($toml);

        // Assert
        self::assertCount(1, $result->nodes);
        $table = $result->nodes[0];
        self::assertInstanceOf(Table::class, $table);
        self::assertInstanceOf(Key::class, $table->name);
        self::assertSame(['table'], $table->name->segments);
        self::assertCount(1, $table->entries);
    }

    public function testParseTableWithCommentCapturesComment(): void
    {
        // Arrange
        $toml = '[table] # table comment';

        // Act
        $result = Toml::parse($toml);

        // Assert
        $table = $result->nodes[0];
        self::assertInstanceOf(Table::class, $table);
        self::assertSame(' table comment', $table->comment);
    }

    public function testParseNestedTableCreatesTableWithDottedName(): void
    {
        // Arrange
        $toml = <<<'TOML'
[parent.child]
key = "value"
TOML;

        // Act
        $result = Toml::parse($toml);

        // Assert
        $table = $result->nodes[0];
        self::assertInstanceOf(Table::class, $table);
        self::assertSame(['parent', 'child'], $table->name->segments);
    }

    public function testParseFindTableReturnsCorrectTable(): void
    {
        // Arrange
        $toml = <<<'TOML'
[database]
port = 5432
TOML;

        // Act
        $result = Toml::parse($toml);

        // Assert
        $table = $result->findTable('database');
        self::assertInstanceOf(Table::class, $table);
        self::assertSame(['database'], $table->name->segments);
    }

    public function testParseFindTableReturnsNullForNonExistentTable(): void
    {
        // Arrange
        $toml = '[table]';

        // Act
        $result = Toml::parse($toml);

        // Assert
        $table = $result->findTable('nonexistent');
        self::assertNull($table);
    }

    // ============================================
    // TableArray Tests
    // ============================================

    public function testParseTableArrayCreatesTableArrayNode(): void
    {
        // Arrange
        $toml = <<<'TOML'
[[products]]
name = "Hammer"
TOML;

        // Act
        $result = Toml::parse($toml);

        // Assert
        self::assertCount(1, $result->nodes);
        $tableArray = $result->nodes[0];
        self::assertInstanceOf(TableArray::class, $tableArray);
        self::assertInstanceOf(Key::class, $tableArray->name);
        self::assertSame(['products'], $tableArray->name->segments);
        self::assertCount(1, $tableArray->entries);
    }

    public function testParseMultipleTableArraysCreatesMultipleNodes(): void
    {
        // Arrange
        $toml = <<<'TOML'
[[products]]
name = "Hammer"

[[products]]
name = "Nail"
TOML;

        // Act
        $result = Toml::parse($toml);

        // Assert
        self::assertCount(2, $result->nodes);
        self::assertContainsOnlyInstancesOf(TableArray::class, $result->nodes);
    }

    // ============================================
    // Position Tracking Tests
    // ============================================

    public function testParseTracksPositionForEntry(): void
    {
        // Arrange
        $toml = 'key = "value"';

        // Act
        $result = Toml::parse($toml);

        // Assert
        $entry = $result->nodes[0];
        self::assertSame(1, $entry->position->line);
        self::assertSame(1, $entry->position->column);
        self::assertGreaterThanOrEqual(0, $entry->position->offset);
    }

    public function testParseTracksPositionForValue(): void
    {
        // Arrange
        $toml = 'key = "value"';

        // Act
        $result = Toml::parse($toml);

        // Assert
        $entry = $result->nodes[0];
        $value = $entry->value;
        self::assertSame(1, $value->position->line);
        self::assertGreaterThan(0, $value->position->column);
    }

    // ============================================
    // Document Methods Tests
    // ============================================

    public function testDocumentToArrayConvertsToPhpArray(): void
    {
        // Arrange
        $toml = 'key = "value"';

        // Act
        $result = Toml::parse($toml);

        // Assert
        $array = $result->toArray();
        self::assertSame(['key' => 'value'], $array);
    }

    public function testDocumentGetEntriesReturnsOnlyEntries(): void
    {
        // Arrange
        $toml = <<<'TOML'
# Comment
key1 = "value1"
key2 = "value2"

[table]
key3 = "value3"
TOML;

        // Act
        $result = Toml::parse($toml);

        // Assert
        $entries = $result->getEntries();
        self::assertContainsOnlyInstancesOf(Entry::class, $entries);
        // Should only get entries from document root, not from tables
        self::assertCount(3, $entries); // 1 comment + 2 key-value pairs
    }

    public function testDocumentFindEntryReturnsCorrectEntry(): void
    {
        // Arrange
        $toml = <<<'TOML'
key1 = "value1"
key2 = "value2"
TOML;

        // Act
        $result = Toml::parse($toml);

        // Assert
        $entry = $result->findEntry('key1');
        self::assertInstanceOf(Entry::class, $entry);
        self::assertSame('key1', $entry->key->getFirstSegment());
    }

    public function testDocumentFindEntryReturnsNullForNonExistentKey(): void
    {
        // Arrange
        $toml = 'key = "value"';

        // Act
        $result = Toml::parse($toml);

        // Assert
        $entry = $result->findEntry('nonexistent');
        self::assertNull($entry);
    }

    // ============================================
    // Table Methods Tests
    // ============================================

    public function testTableFindEntryReturnsCorrectEntry(): void
    {
        // Arrange
        $toml = <<<'TOML'
[database]
host = "localhost"
port = 5432
TOML;

        // Act
        $result = Toml::parse($toml);
        $table = $result->findTable('database');

        // Assert
        self::assertNotNull($table);
        $entry = $table->findEntry('host');
        self::assertInstanceOf(Entry::class, $entry);
        self::assertSame('host', $entry->key->getFirstSegment());
    }

    // ============================================
    // Error Handling Tests
    // ============================================

    #[Group('error-handling')]
    public function testParseThrowsExceptionForDuplicateKeys(): void
    {
        // Arrange
        $toml = <<<'TOML'
key = "value1"
key = "value2"
TOML;

        // Assert (before Act for exceptions)
        $this->expectException(DuplicateKeyException::class);

        // Act
        Toml::parse($toml);
    }

    #[Group('error-handling')]
    public function testParseThrowsExceptionForInvalidDate(): void
    {
        // Arrange
        $toml = 'date = 2024-13-45';

        // Assert (before Act for exceptions)
        $this->expectException(SyntaxException::class);

        // Act
        Toml::parse($toml);
    }

    #[Group('error-handling')]
    public function testParseThrowsExceptionForRedefinedTable(): void
    {
        // Arrange
        $toml = <<<'TOML'
[table]
key = "value"

[table]
key2 = "value2"
TOML;

        // Assert (before Act for exceptions)
        $this->expectException(SyntaxException::class);
        $this->expectExceptionMessage("Table 'table' is already defined");

        // Act
        Toml::parse($toml);
    }

    // ============================================
    // Complex Document Tests
    // ============================================

    public function testParseComplexDocumentWithMultipleNodeTypes(): void
    {
        // Arrange
        $toml = <<<'TOML'
# Root comment
title = "TOML Example"

[owner]
name = "Tom"
dob = 1979-05-27T07:32:00Z

[[products]]
name = "Hammer"
sku = 738594937

[[products]]
name = "Nail"
TOML;

        // Act
        $result = Toml::parse($toml);

        // Assert
        self::assertInstanceOf(Document::class, $result);
        self::assertGreaterThan(0, \count($result->nodes));

        // Document nodes can contain Entry (comments and key-values), Table, and TableArray
        // Check that we have different node types
        $hasComment = false;
        $hasKeyValue = false;
        $hasTable = false;
        $hasTableArray = false;

        foreach ($result->nodes as $node) {
            if ($node instanceof Entry) {
                $hasComment = $hasComment || $node->isComment();
                $hasKeyValue = $hasKeyValue || $node->isKeyValue();
            }
            $hasTable = $hasTable || $node instanceof Table;
            $hasTableArray = $hasTableArray || $node instanceof TableArray;
        }

        self::assertTrue($hasComment, 'Document should contain comment entries');
        self::assertTrue($hasKeyValue, 'Document should contain key-value entries');
        self::assertTrue($hasTable, 'Document should contain Table nodes');
        self::assertTrue($hasTableArray, 'Document should contain TableArray nodes');
    }

    public function testParsePreservesAllInformationForRoundTrip(): void
    {
        // Arrange
        $toml = <<<'TOML'
# Comment
key = "value" # inline
TOML;

        // Act
        $result = Toml::parse($toml);

        // Assert
        self::assertCount(2, $result->nodes);

        $comment = $result->nodes[0];
        self::assertTrue($comment->isComment());
        self::assertSame(' Comment', $comment->comment);

        $entry = $result->nodes[1];
        self::assertTrue($entry->isKeyValue());
        self::assertTrue($entry->hasInlineComment());
        self::assertSame(' inline', $entry->comment);
    }
}
