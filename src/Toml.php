<?php

declare(strict_types=1);

namespace Internal\Toml;

use Internal\Toml\Node\Document;
use Internal\Toml\Parser\Lexer;
use Internal\Toml\Parser\Parser;

/**
 * Public facade for TOML parser.
 */
final class Toml
{
    /**
     * Parse TOML string into Document AST.
     */
    public static function parse(string $toml): Document
    {
        $lexer = new Lexer($toml);
        $parser = new Parser($lexer);

        return $parser->parse();
    }

    /**
     * Parse TOML string into PHP array.
     */
    public static function parseToArray(string $toml): array
    {
        return self::parse($toml)->toArray();
    }

    /**
     * Encode PHP array or JsonSerializable object to TOML Document.
     *
     * @param array<string, mixed>|\JsonSerializable $data
     * @return \Stringable Document that can be cast to TOML string
     */
    public static function encode(array|\JsonSerializable $data): \Stringable
    {
        // Convert JsonSerializable to array
        if ($data instanceof \JsonSerializable) {
            $data = $data->jsonSerialize();
        }

        $converter = new Encoder\ArrayToDocumentConverter();
        return $converter->convert($data);
    }
}
