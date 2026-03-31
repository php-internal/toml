<?php

declare(strict_types=1);

namespace Internal\Toml\Tests\Unit\Encoder;

use Internal\Toml\Encoder\ValueFactory;
use Internal\Toml\Exception\InvalidTypeException;
use Internal\Toml\Node\Value\ArrayValue;
use Internal\Toml\Node\Value\BooleanValue;
use Internal\Toml\Node\Value\DateTimeValue;
use Internal\Toml\Node\Value\FloatValue;
use Internal\Toml\Node\Value\InlineTableValue;
use Internal\Toml\Node\Value\IntegerValue;
use Internal\Toml\Node\Value\StringType;
use Internal\Toml\Node\Value\StringValue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(ValueFactory::class)]
final class ValueFactoryTest extends TestCase
{
    public static function provideIntegers(): \Generator
    {
        yield 'positive integer' => [42];
        yield 'negative integer' => [-42];
        yield 'zero' => [0];
        yield 'large integer' => [1000000];
    }

    public static function provideFloats(): \Generator
    {
        yield 'positive float' => [3.14, '3.14'];
        yield 'negative float' => [-3.14, '-3.14'];
        yield 'zero float' => [0.0, '0'];
        yield 'infinity' => [INF, 'inf'];
        yield 'negative infinity' => [-INF, '-inf'];
        yield 'nan' => [NAN, 'nan'];
    }

    public static function provideUnsupportedTypes(): \Generator
    {
        yield 'resource' => [\fopen('php://memory', 'r'), 'resource (stream)'];
        yield 'object without DateTimeInterface' => [new \stdClass(), 'stdClass'];
        yield 'null' => [null, 'null'];
    }

    public static function provideJsonSerializableData(): \Generator
    {
        yield 'string value' => [
            new class implements \JsonSerializable {
                public function jsonSerialize(): string
                {
                    return 'serialized string';
                }
            },
            StringValue::class,
            'serialized string',
        ];

        yield 'integer value' => [
            new class implements \JsonSerializable {
                public function jsonSerialize(): int
                {
                    return 42;
                }
            },
            IntegerValue::class,
            42,
        ];

        yield 'float value' => [
            new class implements \JsonSerializable {
                public function jsonSerialize(): float
                {
                    return 3.14;
                }
            },
            FloatValue::class,
            3.14,
        ];

        yield 'boolean true' => [
            new class implements \JsonSerializable {
                public function jsonSerialize(): bool
                {
                    return true;
                }
            },
            BooleanValue::class,
            true,
        ];

        yield 'boolean false' => [
            new class implements \JsonSerializable {
                public function jsonSerialize(): bool
                {
                    return false;
                }
            },
            BooleanValue::class,
            false,
        ];

        yield 'indexed array' => [
            new class implements \JsonSerializable {
                public function jsonSerialize(): array
                {
                    return [1, 2, 3];
                }
            },
            ArrayValue::class,
            [1, 2, 3],
        ];

        yield 'associative array' => [
            new class implements \JsonSerializable {
                public function jsonSerialize(): array
                {
                    return ['name' => 'John', 'age' => 30];
                }
            },
            InlineTableValue::class,
            ['name' => 'John', 'age' => 30],
        ];

        yield 'empty array' => [
            new class implements \JsonSerializable {
                public function jsonSerialize(): array
                {
                    return [];
                }
            },
            ArrayValue::class,
            [],
        ];

        yield 'datetime value' => [
            new class implements \JsonSerializable {
                public function jsonSerialize(): \DateTimeImmutable
                {
                    return new \DateTimeImmutable('2024-01-15T10:30:00Z');
                }
            },
            DateTimeValue::class,
            '2024-01-15T10:30:00Z',
        ];
    }

    public static function provideJsonSerializableInvalidData(): \Generator
    {
        yield 'null value' => [
            new class implements \JsonSerializable {
                public function jsonSerialize(): mixed
                {
                    return null;
                }
            },
            'null',
        ];

        yield 'stdClass object' => [
            new class implements \JsonSerializable {
                public function jsonSerialize(): object
                {
                    return new \stdClass();
                }
            },
            'stdClass',
        ];

        yield 'resource' => [
            new class implements \JsonSerializable {
                public function jsonSerialize(): mixed
                {
                    return \fopen('php://memory', 'r');
                }
            },
            'resource (stream)',
        ];
    }
    // ============================================
    // String Value Tests
    // ============================================

    public function testCreateStringReturnsBasicStringForSimpleText(): void
    {
        // Arrange
        $value = 'Hello World';

        // Act
        $result = ValueFactory::create($value);

        // Assert
        self::assertInstanceOf(StringValue::class, $result);
        self::assertSame('Hello World', $result->value);
        self::assertSame(StringType::Literal, $result->type);
    }

    public function testCreateStringReturnsMultilineForTextWithNewlines(): void
    {
        // Arrange
        $value = "Hello\nWorld";

        // Act
        $result = ValueFactory::create($value);

        // Assert
        self::assertInstanceOf(StringValue::class, $result);
        self::assertSame("Hello\nWorld", $result->value);
        // Multiline strings without special escapes use Literal
        self::assertSame(StringType::MultilineLiteral, $result->type);
    }

    public function testCreateStringReturnsBasicForTextWithBackslash(): void
    {
        // Arrange
        $value = 'C:\Users\path';

        // Act
        $result = ValueFactory::create($value);

        // Assert
        self::assertInstanceOf(StringValue::class, $result);
        self::assertSame('C:\Users\path', $result->value);
        // Backslash requires escaping, so uses Basic
        self::assertSame(StringType::Basic, $result->type);
    }

    public function testCreateStringReturnsMultilineLiteralForTextWithNewlines(): void
    {
        // Arrange
        $value = "Line 1\nLine 2\nLine 3";

        // Act
        $result = ValueFactory::create($value);

        // Assert
        self::assertInstanceOf(StringValue::class, $result);
        // Without special escape chars, uses MultilineLiteral
        self::assertSame(StringType::MultilineLiteral, $result->type);
    }

    // ============================================
    // Integer Value Tests
    // ============================================

    #[DataProvider('provideIntegers')]
    public function testCreateIntegerReturnsIntegerValue(int $value): void
    {
        // Act
        $result = ValueFactory::create($value);

        // Assert
        self::assertInstanceOf(IntegerValue::class, $result);
        self::assertSame($value, $result->value);
        self::assertSame((string) $value, $result->raw);
    }

    // ============================================
    // Float Value Tests
    // ============================================

    #[DataProvider('provideFloats')]
    public function testCreateFloatReturnsFloatValue(float $value, string $expectedRaw): void
    {
        // Act
        $result = ValueFactory::create($value);

        // Assert
        self::assertInstanceOf(FloatValue::class, $result);

        // NaN special handling - NaN != NaN, so check separately
        if (\is_nan($value)) {
            self::assertTrue(\is_nan($result->value));
        } else {
            self::assertSame($value, $result->value);
        }

        self::assertSame($expectedRaw, $result->raw);
    }

    // ============================================
    // Boolean Value Tests
    // ============================================

    public function testCreateBooleanReturnsTrueValue(): void
    {
        // Act
        $result = ValueFactory::create(true);

        // Assert
        self::assertInstanceOf(BooleanValue::class, $result);
        self::assertTrue($result->value);
    }

    public function testCreateBooleanReturnsFalseValue(): void
    {
        // Act
        $result = ValueFactory::create(false);

        // Assert
        self::assertInstanceOf(BooleanValue::class, $result);
        self::assertFalse($result->value);
    }

    // ============================================
    // DateTime Value Tests
    // ============================================

    public function testCreateDateTimeReturnsDateTimeValueWithZFormat(): void
    {
        // Arrange
        $value = new \DateTimeImmutable('1979-05-27T07:32:00Z');

        // Act
        $result = ValueFactory::create($value);

        // Assert
        self::assertInstanceOf(DateTimeValue::class, $result);
        self::assertSame('1979-05-27T07:32:00Z', $result->raw);
    }

    public function testCreateDateTimeReturnsDateTimeValueWithOffset(): void
    {
        // Arrange
        $value = new \DateTimeImmutable('2024-01-15T10:30:00+03:00');

        // Act
        $result = ValueFactory::create($value);

        // Assert
        self::assertInstanceOf(DateTimeValue::class, $result);
        self::assertSame('2024-01-15T10:30:00+03:00', $result->raw);
    }

    public function testCreateDateTimeConvertsDateTimeToImmutable(): void
    {
        // Arrange
        $value = new \DateTime('1979-05-27T07:32:00Z');

        // Act
        $result = ValueFactory::create($value);

        // Assert
        self::assertInstanceOf(DateTimeValue::class, $result);
        self::assertInstanceOf(\DateTimeImmutable::class, $result->value);
    }

    // ============================================
    // Array Value Tests
    // ============================================

    public function testCreateArrayReturnsEmptyArrayValue(): void
    {
        // Arrange
        $value = [];

        // Act
        $result = ValueFactory::create($value);

        // Assert
        self::assertInstanceOf(ArrayValue::class, $result);
        self::assertCount(0, $result->elements);
    }

    public function testCreateArrayReturnsArrayValueWithElements(): void
    {
        // Arrange
        $value = [1, 2, 3];

        // Act
        $result = ValueFactory::create($value);

        // Assert
        self::assertInstanceOf(ArrayValue::class, $result);
        self::assertCount(3, $result->elements);
        self::assertContainsOnlyInstancesOf(IntegerValue::class, $result->elements);
    }

    public function testCreateArrayReturnsInlineTableForAssociativeArray(): void
    {
        // Arrange
        $value = ['name' => 'Tom', 'age' => 30];

        // Act
        $result = ValueFactory::create($value);

        // Assert
        self::assertInstanceOf(InlineTableValue::class, $result);
        self::assertCount(2, $result->pairs);
        self::assertArrayHasKey('name', $result->pairs);
        self::assertArrayHasKey('age', $result->pairs);
    }

    public function testCreateArrayHandlesMixedTypes(): void
    {
        // Arrange
        $value = [1, 'text', true, 3.14];

        // Act
        $result = ValueFactory::create($value);

        // Assert
        self::assertInstanceOf(ArrayValue::class, $result);
        self::assertCount(4, $result->elements);
        self::assertInstanceOf(IntegerValue::class, $result->elements[0]);
        self::assertInstanceOf(StringValue::class, $result->elements[1]);
        self::assertInstanceOf(BooleanValue::class, $result->elements[2]);
        self::assertInstanceOf(FloatValue::class, $result->elements[3]);
    }

    // ============================================
    // Error Handling Tests
    // ============================================

    #[DataProvider('provideUnsupportedTypes')]
    public function testCreateThrowsExceptionForUnsupportedTypes(mixed $value, string $expectedType): void
    {
        // Assert (before Act for exceptions)
        $this->expectException(InvalidTypeException::class);
        $this->expectExceptionMessage("Unsupported value type: $expectedType");

        // Act
        ValueFactory::create($value);
    }

    // ============================================
    // Edge Cases Tests
    // ============================================

    public function testCreateStringHandlesEmptyString(): void
    {
        // Act
        $result = ValueFactory::create('');

        // Assert
        self::assertInstanceOf(StringValue::class, $result);
        self::assertSame('', $result->value);
    }

    public function testCreateArrayHandlesNestedArrays(): void
    {
        // Arrange
        $value = [[1, 2], [3, 4]];

        // Act
        $result = ValueFactory::create($value);

        // Assert
        self::assertInstanceOf(ArrayValue::class, $result);
        self::assertCount(2, $result->elements);
        self::assertContainsOnlyInstancesOf(ArrayValue::class, $result->elements);
    }

    public function testCreateArrayHandlesNestedAssociativeArrays(): void
    {
        // Arrange
        $value = ['outer' => ['inner' => 'value']];

        // Act
        $result = ValueFactory::create($value);

        // Assert
        self::assertInstanceOf(InlineTableValue::class, $result);
        self::assertArrayHasKey('outer', $result->pairs);
        self::assertInstanceOf(InlineTableValue::class, $result->pairs['outer']);
    }

    // ============================================
    // JsonSerializable Tests
    // ============================================

    #[DataProvider('provideJsonSerializableData')]
    public function testCreateJsonSerializableReturnsCorrectValueType(
        \JsonSerializable $jsonSerializable,
        string $expectedClass,
        mixed $expectedValue,
    ): void {
        // Act
        $result = ValueFactory::create($jsonSerializable);

        // Assert
        self::assertInstanceOf($expectedClass, $result);

        // Verify the actual value based on type
        match ($expectedClass) {
            StringValue::class => self::assertSame($expectedValue, $result->value),
            IntegerValue::class => self::assertSame($expectedValue, $result->value),
            FloatValue::class => self::assertSame($expectedValue, $result->value),
            BooleanValue::class => self::assertSame($expectedValue, $result->value),
            DateTimeValue::class => self::assertSame($expectedValue, $result->raw),
            ArrayValue::class => self::assertCount(\count($expectedValue), $result->elements),
            InlineTableValue::class => self::assertCount(\count($expectedValue), $result->pairs),
            default => self::fail("Unexpected value class: $expectedClass"),
        };
    }

    #[DataProvider('provideJsonSerializableInvalidData')]
    public function testCreateJsonSerializableThrowsExceptionForInvalidData(
        \JsonSerializable $jsonSerializable,
        string $expectedType,
    ): void {
        // Assert (before Act for exceptions)
        $this->expectException(InvalidTypeException::class);
        $this->expectExceptionMessage("Unsupported value type: $expectedType");

        // Act
        ValueFactory::create($jsonSerializable);
    }

    public function testCreateJsonSerializableWithNestedJsonSerializable(): void
    {
        // Arrange
        $innerJsonSerializable = new class implements \JsonSerializable {
            public function jsonSerialize(): string
            {
                return 'inner value';
            }
        };

        $outerJsonSerializable = new class($innerJsonSerializable) implements \JsonSerializable {
            public function __construct(private readonly \JsonSerializable $inner) {}

            public function jsonSerialize(): array
            {
                return ['nested' => $this->inner];
            }
        };

        // Act
        $result = ValueFactory::create($outerJsonSerializable);

        // Assert
        self::assertInstanceOf(InlineTableValue::class, $result);
        self::assertArrayHasKey('nested', $result->pairs);
        self::assertInstanceOf(StringValue::class, $result->pairs['nested']);
        self::assertSame('inner value', $result->pairs['nested']->value);
    }

    public function testCreateJsonSerializableWithComplexStructure(): void
    {
        // Arrange
        $jsonSerializable = new class implements \JsonSerializable {
            public function jsonSerialize(): array
            {
                return [
                    'title' => 'Test',
                    'count' => 10,
                    'enabled' => true,
                    'ratio' => 0.5,
                    'tags' => ['php', 'toml'],
                    'metadata' => [
                        'created' => '2024-01-15',
                        'version' => 1,
                    ],
                ];
            }
        };

        // Act
        $result = ValueFactory::create($jsonSerializable);

        // Assert
        self::assertInstanceOf(InlineTableValue::class, $result);
        self::assertCount(6, $result->pairs);
        self::assertInstanceOf(StringValue::class, $result->pairs['title']);
        self::assertInstanceOf(IntegerValue::class, $result->pairs['count']);
        self::assertInstanceOf(BooleanValue::class, $result->pairs['enabled']);
        self::assertInstanceOf(FloatValue::class, $result->pairs['ratio']);
        self::assertInstanceOf(ArrayValue::class, $result->pairs['tags']);
        self::assertInstanceOf(InlineTableValue::class, $result->pairs['metadata']);
    }
}
