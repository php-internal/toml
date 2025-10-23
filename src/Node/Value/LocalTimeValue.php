<?php

declare(strict_types=1);

namespace Internal\Toml\Node\Value;

use Internal\Toml\Node\Position;

/**
 * Represents a local time value in TOML.
 */
final class LocalTimeValue extends Value
{
    public function __construct(
        public readonly string $value,    // "07:32:00.999999"
        Position $position,
    ) {
        parent::__construct($position);
    }

    public function toPhpValue(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
