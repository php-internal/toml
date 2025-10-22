<?php

declare(strict_types=1);

namespace Internal\Toml\Node;

/**
 * Represents a position in the TOML source text.
 */
final class Position
{
    public function __construct(
        public readonly int $line,
        public readonly int $column,
        public readonly int $offset,
        public readonly int $length = 0,
    ) {}
}
