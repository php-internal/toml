<?php

declare(strict_types=1);

namespace Internal\Toml\Node\Value;

use Internal\Toml\Node\Position;

/**
 * Represents a date-time value in TOML.
 */
final class DateTimeValue extends Value
{
    public function __construct(
        public readonly \DateTimeImmutable $value,
        public readonly DateTimeType $type,
        public readonly string $raw,
        Position $position,
    ) {
        parent::__construct($position);
    }

    public function toPhpValue(): \DateTimeImmutable
    {
        return $this->value;
    }
}
