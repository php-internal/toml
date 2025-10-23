<?php

declare(strict_types=1);

namespace Internal\Toml\Tests\Unit\Node;

use Internal\Toml\Node\Document;
use Internal\Toml\Toml;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[CoversClass(Document::class)]
#[Group('serialization')]
final class DocumentSerializationTest extends TestCase
{
    public static function provideComplexDocuments(): \Generator
    {
        yield 'nested tables' => [
            <<<'TOML'
            [a]
            x = 1

            [a.b]
            y = 2

            [a.b.c]
            z = 3
            TOML,
        ];

        yield 'table arrays' => [
            <<<'TOML'
            [[items]]
            name = "first"

            [[items]]
            name = "second"
            TOML,
        ];

        yield 'mixed structure' => [
            <<<'TOML'
            root = "value"

            [section]
            key = 1

            [[array]]
            item = "a"

            [[array]]
            item = "b"
            TOML,
        ];
    }

    public function testToStringReturnsValidToml(): void
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

    public function testToStringOutputsRootEntriesFirst(): void
    {
        // Arrange
        $toml = <<<'TOML'
        root_key = "value"

        [table]
        table_key = "value"
        TOML;
        $document = Toml::parse($toml);

        // Act
        $result = $document->__toString();

        // Assert
        $rootPos = \strpos($result, 'root_key');
        $tablePos = \strpos($result, '[table]');
        self::assertLessThan($tablePos, $rootPos);
    }

    public function testToStringAddsBlankLineBetweenRootAndTables(): void
    {
        // Arrange
        $toml = <<<'TOML'
        key = "value"

        [table]
        x = 1
        TOML;
        $document = Toml::parse($toml);

        // Act
        $result = $document->__toString();

        // Assert
        self::assertMatchesRegularExpression('/key = "value"\n\n\[table\]/', $result);
    }

    public function testToStringSeparatesTablesWithBlankLine(): void
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
        $result = $document->__toString();

        // Assert
        self::assertMatchesRegularExpression('/\[table1\].*\n.*\n\n\[table2\]/', $result);
    }

    #[DataProvider('provideComplexDocuments')]
    public function testToStringHandlesComplexStructures(string $toml): void
    {
        // Arrange
        $document = Toml::parse($toml);

        // Act
        $result = $document->__toString();
        $roundTrip = Toml::parse($result);

        // Assert
        self::assertEquals($document->toArray(), $roundTrip->toArray());
    }

    public function testToStringPreservesComments(): void
    {
        // Arrange
        $toml = <<<'TOML'
        # Top comment
        key = "value"
        TOML;
        $document = Toml::parse($toml);

        // Act
        $result = $document->__toString();

        // Assert
        self::assertStringContainsString('# Top comment', $result);
    }
}
