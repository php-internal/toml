<?php

declare(strict_types=1);

namespace Internal\Toml\Node\Value;

use Internal\Toml\Node\Position;

/**
 * Represents a local time value in TOML.
 */
final class LocalTimeValue extends Value
{
    public readonly string $value;

    public function __construct(
        string $value,    // "07:32:00.999999"
        Position $position,
    ) {
        parent::__construct($position);
        // Normalize: inject :00 seconds when omitted (HH:MM → HH:MM:00)
        $this->value = \preg_match('/^\d{2}:\d{2}$/', $value) ? $value . ':00' : $value;
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
