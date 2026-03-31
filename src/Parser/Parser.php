<?php

declare(strict_types=1);

namespace Internal\Toml\Parser;

use Internal\Toml\Exception\DuplicateKeyException;
use Internal\Toml\Exception\SyntaxException;
use Internal\Toml\Node\Document;
use Internal\Toml\Node\Entry;
use Internal\Toml\Node\Key;
use Internal\Toml\Node\Position;
use Internal\Toml\Node\Table;
use Internal\Toml\Node\TableArray;
use Internal\Toml\Node\Value\ArrayValue;
use Internal\Toml\Node\Value\BooleanValue;
use Internal\Toml\Node\Value\DateTimeType;
use Internal\Toml\Node\Value\DateTimeValue;
use Internal\Toml\Node\Value\FloatValue;
use Internal\Toml\Node\Value\InlineTableValue;
use Internal\Toml\Node\Value\IntegerFormat;
use Internal\Toml\Node\Value\IntegerValue;
use Internal\Toml\Node\Value\LocalTimeValue;
use Internal\Toml\Node\Value\StringType;
use Internal\Toml\Node\Value\StringValue;
use Internal\Toml\Node\Value\Value;

/**
 * Builds AST from token stream.
 *
 * @internal
 */
final class Parser
{
    /** @var list<Token> */
    private array $tokens;

    private int $position = 0;

    /** @var array<string, bool> */
    private array $seenKeys = [];

    /** @var array<string, bool> */
    private array $seenTables = [];

    public function __construct(Lexer $lexer)
    {
        $this->tokens = \array_filter(
            $lexer->tokenize(),
            static fn(Token $token): bool => $token->type !== TokenType::Whitespace,
        );
        $this->tokens = \array_values($this->tokens);
    }

    public function parse(): Document
    {
        $nodes = [];
        $position = new Position(1, 1, 0);

        while (!$this->isAtEnd()) {
            $this->skipNewlinesAndComments($nodes);

            if ($this->isAtEnd()) {
                break;
            }

            $token = $this->current();

            if ($token->type === TokenType::LeftBracket) {
                $node = $this->parseTableOrTableArray();
                $nodes[] = $node;
            } elseif ($token->type->isBareKey() or $token->type === TokenType::String) {
                $entry = $this->parseKeyValuePair();
                $nodes[] = $entry;
            } else {
                throw new SyntaxException("Unexpected token {$token->type->value} at line {$token->line}, column {$token->column}");
            }
        }

        return new Document($nodes, $position);
    }

    private function parseTableOrTableArray(): Table|TableArray
    {
        $startToken = $this->consume(TokenType::LeftBracket);
        $isArray = false;

        // Check for [[
        if ($this->check(TokenType::LeftBracket)) {
            $this->advance();
            $isArray = true;
        }

        $key = $this->parseKey();

        // Check for table redefinition (for non-array tables)
        $tableName = $key->__toString();
        if ($isArray) {
            // New array-of-tables element: reset subtable tracking for this prefix
            $prefix = $tableName . '.';
            foreach (\array_keys($this->seenTables) as $seen) {
                if (\str_starts_with($seen, $prefix)) {
                    unset($this->seenTables[$seen]);
                }
            }
        } else {
            if (isset($this->seenTables[$tableName])) {
                throw new SyntaxException("Table '{$tableName}' is already defined at line {$key->position->line}, column {$key->position->column}");
            }
            $this->seenTables[$tableName] = true;
        }

        // Consume closing bracket(s)
        $this->consume(TokenType::RightBracket);

        if ($isArray) {
            $this->consume(TokenType::RightBracket);
        }

        // Optional inline comment
        $comment = null;
        if ($this->check(TokenType::Comment)) {
            $comment = $this->advance()->literal;
        }

        $this->skipNewlines();

        // Reset seen keys for this table scope
        $this->seenKeys = [];

        // Parse entries
        $entries = [];

        while (!$this->isAtEnd() and !$this->check(TokenType::LeftBracket)) {
            $this->skipNewlinesAndComments($entries);

            if ($this->isAtEnd() or $this->check(TokenType::LeftBracket)) {
                break;
            }

            $entry = $this->parseKeyValuePair();
            $entries[] = $entry;
        }

        $position = new Position($startToken->line, $startToken->column, $startToken->position);

        return $isArray
            ? new TableArray($key, $entries, $comment, $position)
            : new Table($key, $entries, $comment, $position);
    }

    private function parseKeyValuePair(): Entry
    {
        $key = $this->parseKey();

        // Check for duplicate keys
        $keyString = $key->__toString();
        if (isset($this->seenKeys[$keyString])) {
            throw new DuplicateKeyException("Duplicate key '{$keyString}' at line {$key->position->line}, column {$key->position->column}");
        }
        $this->seenKeys[$keyString] = true;

        $this->consume(TokenType::Equals);
        $value = $this->parseValue();

        // Optional inline comment
        $comment = null;
        if ($this->check(TokenType::Comment)) {
            $comment = $this->advance()->literal;
        }

        // Key-value pairs must be followed by newline or end of input
        if (!$this->isAtEnd() and !$this->check(TokenType::Newline)) {
            $t = $this->current();
            throw new SyntaxException("Expected newline after value at line {$t->line}, column {$t->column}");
        }

        $this->skipNewlines();

        $position = new Position($key->position->line, $key->position->column, $key->position->offset);

        return new Entry($key, $value, $comment, $position);
    }

    private function parseKey(): Key
    {
        $segments = [];
        $startToken = $this->current();

        do {
            $token = $this->current();

            if ($token->type === TokenType::String) {
                $this->advance();
                $segments[] = $token->literal;
            } elseif ($token->type->isBareKey()) {
                $raw = $this->parseBareKeySegment();
                // Float tokens like "1.2" contain dots that are key separators
                if (\str_contains($raw, '.')) {
                    \array_push($segments, ...\explode('.', $raw));
                } else {
                    $segments[] = $raw;
                }
            } else {
                throw new SyntaxException("Expected key at line {$token->line}, column {$token->column}");
            }

            if ($this->check(TokenType::Dot)) {
                $this->advance();
            } else {
                break;
            }
        } while (true);

        $position = new Position($startToken->line, $startToken->column, $startToken->position);

        return new Key($segments, $position);
    }

    /**
     * Parses a bare key segment, merging adjacent tokens that form a single bare key.
     *
     * Handles cases like "34-11" where the lexer produces Integer("34") + Integer("-11").
     */
    private function parseBareKeySegment(): string
    {
        $token = $this->current();
        $combined = $token->value;
        $this->advance();

        // Merge adjacent tokens that are part of the same bare key (no whitespace between them)
        while (!$this->isAtEnd()) {
            $next = $this->current();
            if ($next->type->isBareKey() && $token->position + \strlen($token->value) === $next->position) {
                $combined .= $next->value;
                $token = $next;
                $this->advance();
            } else {
                break;
            }
        }

        return $combined;
    }

    private function parseValue(): Value
    {
        $token = $this->current();

        return match ($token->type) {
            TokenType::String, TokenType::MultilineString => $this->parseString(),
            TokenType::Integer => $this->parseInteger(),
            TokenType::Float => $this->parseFloat(),
            TokenType::Boolean => $this->parseBoolean(),
            TokenType::Datetime => $this->parseDateTime(),
            TokenType::LeftBracket => $this->parseArray(),
            TokenType::LeftBrace => $this->parseInlineTable(),
            default => throw new SyntaxException("Unexpected token {$token->type->value} at line {$token->line}, column {$token->column}"),
        };
    }

    private function parseString(): StringValue
    {
        $token = $this->consume($this->current()->type);

        $type = $token->type === TokenType::MultilineString
            ? (\str_starts_with($token->value, '"""') ? StringType::MultilineBasic : StringType::MultilineLiteral)
            : (\str_starts_with($token->value, '"') ? StringType::Basic : StringType::Literal);

        $position = new Position($token->line, $token->column, $token->position, \strlen($token->value));

        return new StringValue($token->literal, $type, $position);
    }

    private function parseInteger(): IntegerValue
    {
        $token = $this->consume(TokenType::Integer);

        $format = IntegerFormat::Decimal;

        if (\str_contains($token->value, '0x')) {
            $format = IntegerFormat::Hex;
        } elseif (\str_contains($token->value, '0o')) {
            $format = IntegerFormat::Octal;
        } elseif (\str_contains($token->value, '0b')) {
            $format = IntegerFormat::Binary;
        }

        $position = new Position($token->line, $token->column, $token->position, \strlen($token->value));

        return new IntegerValue($token->literal, $format, $token->value, $position);
    }

    private function parseFloat(): FloatValue
    {
        $token = $this->consume(TokenType::Float);
        $position = new Position($token->line, $token->column, $token->position, \strlen($token->value));

        return new FloatValue($token->literal, $token->value, $position);
    }

    private function parseBoolean(): BooleanValue
    {
        $token = $this->consume(TokenType::Boolean);
        $position = new Position($token->line, $token->column, $token->position, \strlen($token->value));

        return new BooleanValue($token->literal, $position);
    }

    private function parseDateTime(): DateTimeValue|LocalTimeValue
    {
        $token = $this->consume(TokenType::Datetime);
        $position = new Position($token->line, $token->column, $token->position, \strlen($token->value));

        // Check if it's a local time (HH:MM or HH:MM:SS)
        if (\preg_match('/^\d{2}:\d{2}/', $token->value) === 1 and !\str_contains($token->value, '-')) {
            return new LocalTimeValue($token->value, $position);
        }

        // Check if it's a local date (YYYY-MM-DD only)
        if (\preg_match('/^\d{4}-\d{2}-\d{2}$/', $token->value) === 1) {
            $datetime = $token->literal instanceof \DateTimeImmutable ? $token->literal : new \DateTimeImmutable($token->value);
            return new DateTimeValue($datetime, DateTimeType::LocalDate, $token->value, $position);
        }

        // Check if it has timezone offset
        $hasTimezone = \str_contains($token->value, 'Z')
            || \str_contains($token->value, 'z')
            || \preg_match('/[+-]\d{2}:\d{2}$/', $token->value) === 1
            || \preg_match('/[+-]\d{4}$/', $token->value) === 1;

        $type = $hasTimezone ? DateTimeType::OffsetDatetime : DateTimeType::LocalDatetime;
        $datetime = $token->literal instanceof \DateTimeImmutable ? $token->literal : new \DateTimeImmutable($token->value);

        return new DateTimeValue($datetime, $type, $token->value, $position);
    }

    private function parseArray(): ArrayValue
    {
        $startToken = $this->consume(TokenType::LeftBracket);
        $elements = [];

        $this->skipNewlinesAndCommentsDiscarding();

        while (!$this->check(TokenType::RightBracket) and !$this->isAtEnd()) {
            $elements[] = $this->parseValue();

            $this->skipNewlinesAndCommentsDiscarding();

            if ($this->check(TokenType::Comma)) {
                $this->advance();
                $this->skipNewlinesAndCommentsDiscarding();

                // Allow trailing comma
                if ($this->check(TokenType::RightBracket)) {
                    break;
                }
            } elseif (!$this->check(TokenType::RightBracket)) {
                throw new SyntaxException("Expected comma or closing bracket at line {$this->current()->line}, column {$this->current()->column}");
            }
        }

        $this->consume(TokenType::RightBracket);

        $position = new Position($startToken->line, $startToken->column, $startToken->position);

        return new ArrayValue($elements, $position);
    }

    private function parseInlineTable(): InlineTableValue
    {
        $startToken = $this->consume(TokenType::LeftBrace);
        $pairs = [];

        $this->skipNewlinesAndCommentsDiscarding();

        while (!$this->check(TokenType::RightBrace) and !$this->isAtEnd()) {
            $key = $this->parseKey();
            $this->consume(TokenType::Equals);
            $value = $this->parseValue();

            $pairs[\implode('.', $key->segments)] = $value;

            $this->skipNewlinesAndCommentsDiscarding();

            if ($this->check(TokenType::Comma)) {
                $this->advance();
                $this->skipNewlinesAndCommentsDiscarding();

                // Allow trailing comma
                if ($this->check(TokenType::RightBrace)) {
                    break;
                }
            } elseif (!$this->check(TokenType::RightBrace)) {
                throw new SyntaxException("Expected comma or closing brace at line {$this->current()->line}, column {$this->current()->column}");
            }
        }

        $this->consume(TokenType::RightBrace);

        $position = new Position($startToken->line, $startToken->column, $startToken->position);

        return new InlineTableValue($pairs, $position);
    }

    /**
     * @param list<Entry|Table|TableArray> $nodes
     */
    private function skipNewlinesAndComments(array &$nodes): void
    {
        while ($this->check(TokenType::Newline) or $this->check(TokenType::Comment)) {
            if ($this->check(TokenType::Comment)) {
                $token = $this->advance();
                $position = new Position($token->line, $token->column, $token->position);
                $nodes[] = new Entry(null, null, $token->literal, $position);
            } else {
                $this->advance();
            }
        }
    }

    private function skipNewlinesAndCommentsDiscarding(): void
    {
        while ($this->check(TokenType::Newline) or $this->check(TokenType::Comment)) {
            $this->advance();
        }
    }

    private function skipNewlines(): void
    {
        while ($this->check(TokenType::Newline)) {
            $this->advance();
        }
    }

    private function current(): Token
    {
        return $this->tokens[$this->position];
    }

    private function advance(): Token
    {
        $token = $this->current();
        if (!$this->isAtEnd()) {
            $this->position++;
        }
        return $token;
    }

    private function check(TokenType $type): bool
    {
        return !$this->isAtEnd() and $this->current()->type === $type;
    }

    private function consume(TokenType $type): Token
    {
        if (!$this->check($type)) {
            $current = $this->current();
            throw new SyntaxException("Expected {$type->value} but got {$current->type->value} at line {$current->line}, column {$current->column}");
        }

        return $this->advance();
    }

    private function isAtEnd(): bool
    {
        return $this->position >= \count($this->tokens) or $this->current()->type === TokenType::Eof;
    }
}
