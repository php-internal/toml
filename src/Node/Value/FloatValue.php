<?php

declare(strict_types=1);

namespace Internal\Toml\Node\Value;

use Internal\Toml\Node\Position;

/**
 * Represents a float value in TOML.
 */
final class FloatValue extends Value
{
    public function __construct(
        public readonly float $value,
        public readonly string $raw,
        Position $position,
    ) {
        parent::__construct($position);
    }

    public function toPhpValue(): float
    {
        return $this->value;
    }

    public function isInfinity(): bool
    {
        return \is_infinite($this->value);
    }

    public function isNaN(): bool
    {
        return \is_nan($this->value);
    }

    public function __toString(): string
    {
        return $this->raw;
    }
}
