<?php

declare(strict_types=1);

namespace Internal\Toml\Parser;

/**
 * Represents a single token in the TOML source.
 *
 * @internal
 */
final class Token
{
    public function __construct(
        public readonly TokenType $type,
        public readonly string $value,
        public readonly mixed $literal,
        public readonly int $line,
        public readonly int $column,
        public readonly int $position,
    ) {}
}
