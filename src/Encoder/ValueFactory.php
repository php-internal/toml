<?php

declare(strict_types=1);

namespace Internal\Toml\Encoder;

use Internal\Toml\Exception\InvalidTypeException;
use Internal\Toml\Node\Position;
use Internal\Toml\Node\Value\ArrayValue;
use Internal\Toml\Node\Value\BooleanValue;
use Internal\Toml\Node\Value\DateTimeType;
use Internal\Toml\Node\Value\DateTimeValue;
use Internal\Toml\Node\Value\FloatValue;
use Internal\Toml\Node\Value\InlineTableValue;
use Internal\Toml\Node\Value\IntegerFormat;
use Internal\Toml\Node\Value\IntegerValue;
use Internal\Toml\Node\Value\StringType;
use Internal\Toml\Node\Value\StringValue;
use Internal\Toml\Node\Value\Value;

/**
 * Creates TOML Value nodes from PHP values.
 *
 * @internal
 */
final class ValueFactory
{
    public static function create(mixed $value): Value
    {
        return match (true) {
            $value instanceof Value => $value,
            \is_string($value) => self::createString($value),
            \is_int($value) => self::createInteger($value),
            \is_float($value) => self::createFloat($value),
            \is_bool($value) => self::createBoolean($value),
            $value instanceof \DateTimeInterface => self::createDateTime($value),
            \is_array($value) => self::createArray($value),
            $value instanceof \JsonSerializable => self::create($value->jsonSerialize()),
            default => throw new InvalidTypeException('Unsupported value type: ' . \get_debug_type($value)),
        };
    }

    private static function createString(string $value): StringValue
    {
        $type = self::determineStringType($value);
        $position = new Position(0, 0, 0);

        return new StringValue($value, $type, $position);
    }

    private static function createInteger(int $value): IntegerValue
    {
        $position = new Position(0, 0, 0);
        return new IntegerValue($value, IntegerFormat::Decimal, (string) $value, $position);
    }

    private static function createFloat(float $value): FloatValue
    {
        $position = new Position(0, 0, 0);
        $raw = match (true) {
            \is_infinite($value) && $value > 0 => 'inf',
            \is_infinite($value) && $value < 0 => '-inf',
            \is_nan($value) => 'nan',
            default => (string) $value,
        };

        return new FloatValue($value, $raw, $position);
    }

    private static function createBoolean(bool $value): BooleanValue
    {
        $position = new Position(0, 0, 0);
        return new BooleanValue($value, $position);
    }

    private static function createDateTime(\DateTimeInterface $value): DateTimeValue
    {
        $position = new Position(0, 0, 0);
        $immutable = $value instanceof \DateTimeImmutable
            ? $value
            : \DateTimeImmutable::createFromMutable($value);

        // Determine datetime type based on timezone
        $timezone = $value->getTimezone();
        $offset = $value->format('P');

        $type = DateTimeType::OffsetDatetime;

        // Check if it's UTC (offset +00:00)
        if ($offset === '+00:00') {
            $raw = $value->format('Y-m-d\TH:i:s') . 'Z';
        } else {
            // Use RFC3339 format without microseconds
            $raw = $value->format('Y-m-d\TH:i:sP');
        }

        return new DateTimeValue($immutable, $type, $raw, $position);
    }

    private static function createArray(array $value): ArrayValue|InlineTableValue
    {
        $position = new Position(0, 0, 0);

        // Empty array → empty array value
        if ($value === []) {
            return new ArrayValue([], $position);
        }

        // Associative array → inline table
        if (!\array_is_list($value)) {
            $pairs = [];
            foreach ($value as $k => $v) {
                $pairs[(string) $k] = self::create($v);
            }
            return new InlineTableValue($pairs, $position);
        }

        // Indexed array → array value
        $elements = \array_map(
            static fn(mixed $item): Value => self::create($item),
            $value,
        );

        return new ArrayValue($elements, $position);
    }

    /**
     * Determines the optimal string type for the given value.
     */
    private static function determineStringType(string $value): StringType
    {
        // Check for newlines
        $hasNewlines = \str_contains($value, "\n") or \str_contains($value, "\r");

        if ($hasNewlines) {
            // Multiline string
            $needsEscapes = self::needsEscaping($value);
            return $needsEscapes
                ? StringType::MultilineBasic
                : StringType::MultilineLiteral;
        }

        // Single-line string
        $needsEscapes = self::needsEscaping($value);
        return $needsEscapes ? StringType::Basic : StringType::Literal;
    }

    /**
     * Checks if a string contains characters that need escaping.
     */
    private static function needsEscaping(string $value): bool
    {
        // Check for control characters (except tab in literals), backslash, or quotes
        return \preg_match('/[\x00-\x08\x0B-\x1F\x7F\\\\"]/', $value) === 1;
    }
}
