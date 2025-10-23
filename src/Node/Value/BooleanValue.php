<?php

declare(strict_types=1);

namespace Internal\Toml\Node\Value;

use Internal\Toml\Node\Position;

/**
 * Represents a boolean value in TOML.
 */
final class BooleanValue extends Value
{
    public function __construct(
        public readonly bool $value,
        Position $position,
    ) {
        parent::__construct($position);
    }

    public function toPhpValue(): bool
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value ? 'true' : 'false';
    }
}
