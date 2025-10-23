<?php

declare(strict_types=1);

namespace Internal\Toml\Encoder;

use Internal\Toml\Node\Document;
use Internal\Toml\Node\Entry;
use Internal\Toml\Node\Key;
use Internal\Toml\Node\Position;
use Internal\Toml\Node\Table;
use Internal\Toml\Node\TableArray;

/**
 * Converts PHP array to TOML Document AST.
 *
 * @internal
 */
final class ArrayToDocumentConverter
{
    /**
     * @param array<string, mixed> $data
     */
    public function convert(array $data): Document
    {
        $nodes = [];
        $position = new Position(1, 1, 0);

        // Categorize data into root entries, tables, and table arrays
        [$rootEntries, $tables, $tableArrays] = $this->categorizeData($data);

        // Root key-value pairs first
        foreach ($rootEntries as $key => $value) {
            $nodes[] = $this->createEntry($key, $value);
        }

        // Regular tables
        foreach ($tables as $tableName => $tableData) {
            $nodes[] = $this->createTable($tableName, $tableData);
        }

        // Table arrays
        foreach ($tableArrays as $arrayName => $arrayItems) {
            foreach ($arrayItems as $item) {
                $nodes[] = $this->createTableArray($arrayName, $item);
            }
        }

        return new Document($nodes, $position);
    }

    /**
     * Categorizes data into root entries, tables, and table arrays.
     *
     * @param array<string, mixed> $data
     * @return array{0: array<string, mixed>, 1: array<string, array>, 2: array<string, list<array>>}
     */
    private function categorizeData(array $data): array
    {
        $rootEntries = [];
        $tables = [];
        $tableArrays = [];

        foreach ($data as $key => $value) {
            if (!\is_string($key)) {
                throw new \InvalidArgumentException('TOML keys must be strings, got: ' . \get_debug_type($key));
            }

            if (!\is_array($value)) {
                // Scalar value → root entry
                $rootEntries[$key] = $value;
            } elseif ($this->isTableArray($value)) {
                // List of associative arrays → table array
                $tableArrays[$key] = $value;
            } elseif ($this->isAssociativeArray($value)) {
                // Associative array → table (unless it should be inline)
                $tables[$key] = $value;
            } else {
                // Simple array → root entry with array value
                $rootEntries[$key] = $value;
            }
        }

        return [$rootEntries, $tables, $tableArrays];
    }

    private function createEntry(string $key, mixed $value): Entry
    {
        $keyNode = $this->createKey($key);
        $valueNode = ValueFactory::create($value);
        $position = new Position(0, 0, 0);

        return new Entry($keyNode, $valueNode, null, $position);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function createTable(string $name, array $data): Table
    {
        $keyNode = $this->createKey($name);
        $entries = [];
        $position = new Position(0, 0, 0);

        // Categorize table data
        [$rootEntries, $nestedTables, $nestedTableArrays] = $this->categorizeData($data);

        // Add root entries
        foreach ($rootEntries as $key => $value) {
            $entries[] = $this->createEntry($key, $value);
        }

        // Nested tables and table arrays will be handled at document level
        // For now, we only handle simple table entries
        // TODO: Handle nested structures properly

        return new Table($keyNode, $entries, null, $position);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function createTableArray(string $name, array $data): TableArray
    {
        $keyNode = $this->createKey($name);
        $entries = [];
        $position = new Position(0, 0, 0);

        foreach ($data as $key => $value) {
            if (!\is_string($key)) {
                throw new \InvalidArgumentException('TOML keys must be strings, got: ' . \get_debug_type($key));
            }

            $entries[] = $this->createEntry($key, $value);
        }

        return new TableArray($keyNode, $entries, null, $position);
    }

    private function createKey(string $path): Key
    {
        // Support dotted keys: "a.b.c" → ["a", "b", "c"]
        $segments = \explode('.', $path);
        $position = new Position(0, 0, 0);

        return new Key($segments, $position);
    }

    /**
     * Determines if an array is associative (not a list).
     */
    private function isAssociativeArray(array $arr): bool
    {
        return $arr !== [] and !\array_is_list($arr);
    }

    /**
     * Determines if an array is a table array.
     * Table array: list of associative arrays.
     *
     * Example: [['name' => 'A'], ['name' => 'B']]
     */
    private function isTableArray(array $arr): bool
    {
        if (!\array_is_list($arr) or $arr === []) {
            return false;
        }

        // Check if all elements are associative arrays
        foreach ($arr as $item) {
            if (!\is_array($item) or !$this->isAssociativeArray($item)) {
                return false;
            }
        }

        return true;
    }
}
