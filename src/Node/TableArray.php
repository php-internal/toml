<?php

declare(strict_types=1);

namespace Internal\Toml\Node;

/**
 * Represents an array of tables [[array]] in TOML.
 */
final class TableArray extends Node implements MultiLineNode
{
    /**
     * @param list<Entry> $entries
     */
    public function __construct(
        public readonly Key $name,
        public readonly array $entries,
        public readonly ?string $comment,
        Position $position,
    ) {
        parent::__construct($position);
    }

    /**
     * @return list<Entry>
     */
    public function getEntries(): array
    {
        return $this->entries;
    }

    public function findEntry(string $key): ?Entry
    {
        foreach ($this->entries as $entry) {
            if ($entry->key?->toString() === $key) {
                return $entry;
            }
        }

        return null;
    }
}
