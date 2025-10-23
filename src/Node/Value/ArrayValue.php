<?php

declare(strict_types=1);

namespace Internal\Toml\Node\Value;

use Internal\Toml\Node\Entry;
use Internal\Toml\Node\MultiLineNode;
use Internal\Toml\Node\Position;

/**
 * Represents an array value in TOML.
 */
final class ArrayValue extends Value implements MultiLineNode
{
    /**
     * @param list<Value> $elements
     */
    public function __construct(
        public readonly array $elements,
        Position $position,
    ) {
        parent::__construct($position);
    }

    public function toPhpValue(): array
    {
        return \array_map(
            static fn(Value $element): mixed => $element->toPhpValue(),
            $this->elements,
        );
    }

    /**
     * @return list<Entry>
     */
    public function getEntries(): array
    {
        return [];
    }

    public function isEmpty(): bool
    {
        return $this->elements === [];
    }

    public function count(): int
    {
        return \count($this->elements);
    }

    public function __toString(): string
    {
        if ($this->elements === []) {
            return '[]';
        }

        $parts = [];
        foreach ($this->elements as $element) {
            $parts[] = (string) $element;
        }

        return '[' . \implode(', ', $parts) . ']';
    }
}
