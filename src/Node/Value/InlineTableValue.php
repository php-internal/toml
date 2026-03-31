<?php

declare(strict_types=1);

namespace Internal\Toml\Node\Value;

use Internal\Toml\Node\Entry;
use Internal\Toml\Node\Key;
use Internal\Toml\Node\MultiLineNode;
use Internal\Toml\Node\Position;

/**
 * Represents an inline table value in TOML.
 */
final class InlineTableValue extends Value implements MultiLineNode
{
    /**
     * @param array<string, Value> $pairs
     */
    public function __construct(
        public readonly array $pairs,
        Position $position,
    ) {
        parent::__construct($position);
    }

    public function toPhpValue(): array
    {
        $result = [];

        foreach ($this->pairs as $key => $value) {
            $result[$key] = $value->toPhpValue();
        }

        return $result;
    }

    /**
     * @return list<Entry>
     */
    public function getEntries(): array
    {
        return [];
    }

    public function has(string $key): bool
    {
        return isset($this->pairs[$key]);
    }

    public function get(string $key): ?Value
    {
        return $this->pairs[$key] ?? null;
    }

    public function __toString(): string
    {
        if ($this->pairs === []) {
            return '{}';
        }

        $parts = [];
        foreach ($this->pairs as $key => $value) {
            $parts[] = Key::quoteIfNeeded($key) . ' = ' . (string) $value;
        }

        return '{' . \implode(', ', $parts) . '}';
    }
}
