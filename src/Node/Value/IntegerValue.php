<?php

declare(strict_types=1);

namespace Internal\Toml\Node\Value;

use Internal\Toml\Node\Position;

/**
 * Represents an integer value in TOML.
 */
final class IntegerValue extends Value
{
    public function __construct(
        public readonly int $value,
        public readonly IntegerFormat $format,
        public readonly string $raw,      // original representation (with underscores)
        Position $position,
    ) {
        parent::__construct($position);
    }

    public function toPhpValue(): int
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->raw;
    }
}
