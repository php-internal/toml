<?php

declare(strict_types=1);

namespace Internal\Toml\Node;

/**
 * Root node representing the entire TOML document (root table).
 */
final class Document extends Node implements MultiLineNode
{
    /**
     * @param list<Entry|Table|TableArray> $nodes
     */
    public function __construct(
        public readonly array $nodes,
        Position $position,
    ) {
        parent::__construct($position);
    }

    /**
     * @return list<Entry>
     */
    public function getEntries(): array
    {
        return \array_filter(
            $this->nodes,
            static fn(mixed $node): bool => $node instanceof Entry,
        );
    }

    /**
     * Convert the document to a PHP array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $result = [];

        foreach ($this->nodes as $node) {
            match (true) {
                $node instanceof Entry && $node->isKeyValue() => $this->addEntry($result, $node),
                $node instanceof Table => $this->addTable($result, $node),
                $node instanceof TableArray => $this->addTableArray($result, $node),
                default => null,
            };
        }

        return $result;
    }

    public function findTable(string $name): ?Table
    {
        foreach ($this->nodes as $node) {
            if ($node instanceof Table and $node->name->__toString() === $name) {
                return $node;
            }
        }

        return null;
    }

    public function findEntry(string $key): ?Entry
    {
        foreach ($this->nodes as $node) {
            if ($node instanceof Entry and $node->key?->__toString() === $key) {
                return $node;
            }
        }

        return null;
    }

    public function __toString(): string
    {
        $result = '';
        $hasRootEntries = false;

        // First, output all root-level entries (before any tables)
        foreach ($this->nodes as $node) {
            if ($node instanceof Entry) {
                $entryStr = (string) $node;
                if ($entryStr !== '') {
                    $result .= $entryStr . "\n";
                    $hasRootEntries = true;
                }
            } else {
                // Stop at first table
                break;
            }
        }

        // Add blank line after root entries if there are tables
        if ($hasRootEntries && $this->hasTablesOrTableArrays()) {
            $result .= "\n";
        }

        // Output all tables and table arrays
        $firstTable = true;
        foreach ($this->nodes as $node) {
            if ($node instanceof Table or $node instanceof TableArray) {
                if (!$firstTable) {
                    $result .= "\n";
                }
                $result .= (string) $node;
                $firstTable = false;
            }
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $result
     */
    private function addEntry(array &$result, Entry $entry): void
    {
        if ($entry->key === null or $entry->value === null) {
            return;
        }

        $key = $entry->key;

        if ($key->isSimple()) {
            $result[$key->getFirstSegment()] = $entry->value->toPhpValue();
            return;
        }

        // Handle dotted keys
        $current = &$result;
        $segments = $key->segments;
        $lastIndex = \count($segments) - 1;

        foreach ($segments as $i => $segment) {
            if ($i === $lastIndex) {
                $current[$segment] = $entry->value->toPhpValue();
            } else {
                $current[$segment] ??= [];
                $current = &$current[$segment];
            }
        }
    }

    /**
     * @param array<string, mixed> $result
     */
    private function addTable(array &$result, Table $table): void
    {
        $current = &$result;

        foreach ($table->name->segments as $segment) {
            $current[$segment] ??= [];
            $current = &$current[$segment];
        }

        foreach ($table->entries as $entry) {
            if ($entry->isKeyValue() and $entry->key !== null and $entry->value !== null) {
                if ($entry->key->isSimple()) {
                    $current[$entry->key->getFirstSegment()] = $entry->value->toPhpValue();
                } else {
                    $this->addDottedKey($current, $entry->key, $entry->value);
                }
            }
        }
    }

    /**
     * @param array<string, mixed> $result
     */
    private function addTableArray(array &$result, TableArray $tableArray): void
    {
        $current = &$result;
        $segments = $tableArray->name->segments;
        $lastIndex = \count($segments) - 1;

        foreach ($segments as $i => $segment) {
            if ($i === $lastIndex) {
                // Last segment - create new array element
                $current[$segment] ??= [];
                $current[$segment][] = [];
                $current = &$current[$segment][\count($current[$segment]) - 1];
            } else {
                // Not last segment - navigate to existing structure
                $current[$segment] ??= [];

                // If current segment points to an array, take the LAST element
                if (\is_array($current[$segment]) and \array_is_list($current[$segment]) and $current[$segment] !== []) {
                    $current = &$current[$segment][\count($current[$segment]) - 1];
                } else {
                    $current = &$current[$segment];
                }
            }
        }

        foreach ($tableArray->entries as $entry) {
            if ($entry->isKeyValue() and $entry->key !== null and $entry->value !== null) {
                if ($entry->key->isSimple()) {
                    $current[$entry->key->getFirstSegment()] = $entry->value->toPhpValue();
                } else {
                    $this->addDottedKey($current, $entry->key, $entry->value);
                }
            }
        }
    }

    /**
     * @param array<string, mixed> $current
     */
    private function addDottedKey(array &$current, Key $key, Value\Value $value): void
    {
        $ref = &$current;
        $segments = $key->segments;
        $lastIndex = \count($segments) - 1;

        foreach ($segments as $i => $segment) {
            if ($i === $lastIndex) {
                $ref[$segment] = $value->toPhpValue();
            } else {
                $ref[$segment] ??= [];
                $ref = &$ref[$segment];
            }
        }
    }

    private function hasTablesOrTableArrays(): bool
    {
        foreach ($this->nodes as $node) {
            if ($node instanceof Table or $node instanceof TableArray) {
                return true;
            }
        }
        return false;
    }
}
