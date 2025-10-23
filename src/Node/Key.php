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
     */
    public function __construct(
        public readonly array $segments,
        Position $position,
    ) {
        parent::__construct($position);
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

    public function getFirstSegment(): string
    {
        return $this->segments[0];
    }

    public function getLastSegment(): string
    {
        return $this->segments[\count($this->segments) - 1];
    }

    public function __toString(): string
    {
        $result = [];

        foreach ($this->segments as $segment) {
            $result[] = self::needsQuoting($segment)
                ? '"' . \addcslashes($segment, "\"\\\n\r\t") . '"'
                : $segment;
        }

        return \implode('.', $result);
    }

    /**
     * Determines if a segment needs to be quoted.
     *
     * Bare keys can only contain: A-Za-z0-9_-
     */
    private static function needsQuoting(string $segment): bool
    {
        return $segment === '' or \preg_match('/^[A-Za-z0-9_-]+$/', $segment) !== 1;
    }
}
