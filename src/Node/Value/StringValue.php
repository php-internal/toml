<?php

declare(strict_types=1);

namespace Internal\Toml\Node\Value;

use Internal\Toml\Node\Position;

/**
 * Represents a string value in TOML.
 */
final class StringValue extends Value
{
    public function __construct(
        public readonly string $value,
        public readonly StringType $type,
        Position $position,
    ) {
        parent::__construct($position);
    }

    public function toPhpValue(): string
    {
        return $this->value;
    }
}
