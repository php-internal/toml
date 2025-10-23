<?php

declare(strict_types=1);

namespace Internal\Toml\Node;

/**
 * Finds values in the Document AST by key path (read-only).
 * Supports simple keys, dotted keys, nested tables, and array indices.
 *
 * @internal
 */
final class ValueFinder
{
    public function __construct(
        private readonly Document $document,
    ) {}

    /**
     * Find value by key path.
     *
     * Supports:
     * - Simple keys: 'name'
     * - Dotted keys: 'database.host'
     * - Nested tables: 'package.dependencies.php'
     * - Array indices: 'products.0.name'
     * - Quoted keys: '"key.with.dots".subkey'
     *
     * @param string $path Key path (can be dotted)
     * @return mixed PHP value or null if not found
     */
    public function get(string $path): mixed
    {
        if ($path === '') {
            throw new \InvalidArgumentException('Key path cannot be empty');
        }

        $segments = $this->parsePath($path);

        // Use toArray() for all cases to handle quoted keys correctly
        return $this->getNested($segments);
    }

    /**
     * Check if path exists and has a non-null value.
     */
    public function exists(string $path): bool
    {
        if ($path === '') {
            return false;
        }

        try {
            $value = $this->get($path);
            return $value !== null;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Get nested value by path segments.
     *
     * @param list<string> $segments
     */
    private function getNested(array $segments): mixed
    {
        // For complex nested paths, convert to array and navigate
        // This handles cases where intermediate tables don't exist as AST nodes
        $array = $this->document->toArray();

        foreach ($segments as $segment) {
            // Handle array indices
            if (\ctype_digit($segment)) {
                $index = (int) $segment;

                if (!\is_array($array) || !isset($array[$index])) {
                    return null;
                }

                $array = $array[$index];
                continue;
            }

            // Handle regular keys
            if (!\is_array($array) || !isset($array[$segment])) {
                return null;
            }

            $array = $array[$segment];
        }

        return $array;
    }

    /**
     * Parse dotted path into segments.
     *
     * Handles:
     * - Simple keys: 'name' → ['name']
     * - Dotted keys: 'a.b.c' → ['a', 'b', 'c']
     * - Quoted keys: '"key.with.dots".subkey' → ['key.with.dots', 'subkey']
     * - Mixed: 'a."b.c".d' → ['a', 'b.c', 'd']
     *
     * Note: Quotes are removed from segments during parsing.
     *
     * @return list<string>
     */
    private function parsePath(string $path): array
    {
        $segments = [];
        $current = '';
        $inQuotes = false;
        $length = \strlen($path);

        for ($i = 0; $i < $length; $i++) {
            $char = $path[$i];

            if ($char === '"' && ($i === 0 || $path[$i - 1] !== '\\')) {
                // Toggle quote state (but don't add quote to segment)
                $inQuotes = !$inQuotes;
                continue;
            }

            if ($char === '.' && !$inQuotes) {
                // Dot separator outside quotes
                $current !== '' and $segments[] = $current;
                $current = '';
                continue;
            }

            // Add character to current segment
            $current .= $char;
        }

        // Add last segment
        $current !== '' and $segments[] = $current;

        // Handle empty segments (e.g., "a..b" or ".a" or "a.")
        $segments === [] and throw new \InvalidArgumentException('Invalid key path: empty segments');

        return $segments;
    }
}
