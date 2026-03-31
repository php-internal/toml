<?php

declare(strict_types=1);

namespace Internal\Toml\Node\Value;

use Internal\Toml\Node\Position;

/**
 * Represents a date-time value in TOML.
 */
final class DateTimeValue extends Value
{
    public readonly string $raw;

    public function __construct(
        public readonly \DateTimeImmutable $value,
        public readonly DateTimeType $type,
        string $raw,
        Position $position,
    ) {
        parent::__construct($position);
        $this->raw = self::normalize($raw);
    }

    public function toPhpValue(): \DateTimeImmutable
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->raw;
    }

    /**
     * Normalizes the raw datetime string:
     * - Replaces space/t separator with T
     * - Injects :00 seconds when omitted
     */
    private static function normalize(string $raw): string
    {
        // Replace space or lowercase 't' separator with 'T'
        $raw = \preg_replace('/^(\d{4}-\d{2}-\d{2})[ t]/', '$1T', $raw);

        // Inject :00 seconds when omitted (HH:MM followed by offset or end)
        $raw = \preg_replace('/(T)(\d{2}:\d{2})([Z+\-]|$)/', '$1$2:00$3', $raw);

        return $raw;
    }
}
