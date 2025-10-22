<?php

declare(strict_types=1);

namespace Internal\Toml\Node;

/**
 * Represents a key (simple or dotted) in TOML.
 */
final class Key extends Node
{
    /**
     * @param list<string> $segments - key path segments
     * @param list<KeyType> $types - type for each segment (Bare or Quoted)
     */
    public function __construct(
        public readonly array $segments,
        public readonly array $types,
        Position $position,
    ) {
        parent::__construct($position);
    }

    public function toString(): string
    {
        $result = [];

        foreach ($this->segments as $i => $segment) {
            $result[] = $this->types[$i] === KeyType::Quoted
                ? '"' . $segment . '"'
                : $segment;
        }

        return \implode('.', $result);
    }

    public function isSimple(): bool
    {
        return \count($this->segments) === 1;
    }

    public function isDotted(): bool
    {
        return \count($this->segments) > 1;
    }

    public function getSegment(int $index): string
    {
        return $this->segments[$index];
    }

    public function getType(int $index): KeyType
    {
        return $this->types[$index];
    }

    public function getFirstSegment(): string
    {
        return $this->segments[0];
    }

    public function getLastSegment(): string
    {
        return $this->segments[\count($this->segments) - 1];
    }
}
