<?php

declare(strict_types=1);

namespace Internal\Toml\Parser;

use Internal\Toml\Exception\SyntaxException;

/**
 * Tokenizes TOML input into a stream of tokens.
 *
 * @internal
 */
final class Lexer
{
    private int $position = 0;
    private int $line = 1;
    private int $column = 1;
    private int $length;

    public function __construct(
        private readonly string $input,
    ) {
        $this->length = \strlen($this->input);
    }

    public function nextToken(): Token
    {
        if ($this->position >= $this->length) {
            return $this->makeToken(TokenType::Eof, '', null);
        }

        $char = $this->current();

        // Skip whitespace (except newlines)
        if ($char === ' ' or $char === "\t") {
            return $this->scanWhitespace();
        }

        // Newline
        if ($char === "\n" or $char === "\r") {
            return $this->scanNewline();
        }

        // Comment
        if ($char === '#') {
            return $this->scanComment();
        }

        // Single-character tokens
        return match ($char) {
            '=' => $this->makeSingleCharToken(TokenType::Equals),
            '.' => $this->makeSingleCharToken(TokenType::Dot),
            ',' => $this->makeSingleCharToken(TokenType::Comma),
            '[' => $this->makeSingleCharToken(TokenType::LeftBracket),
            ']' => $this->makeSingleCharToken(TokenType::RightBracket),
            '{' => $this->makeSingleCharToken(TokenType::LeftBrace),
            '}' => $this->makeSingleCharToken(TokenType::RightBrace),
            '"' => $this->scanString('"'),
            "'" => $this->scanString("'"),
            default => $this->scanValueOrKey(),
        };
    }

    /**
     * @return list<Token>
     */
    public function tokenize(): array
    {
        $tokens = [];

        while (true) {
            $token = $this->nextToken();
            $tokens[] = $token;

            if ($token->type === TokenType::Eof) {
                break;
            }
        }

        return $tokens;
    }

    private function current(): string
    {
        return $this->position < $this->length
            ? $this->input[$this->position]
            : '';
    }

    private function peek(int $offset = 1): string
    {
        $pos = $this->position + $offset;
        return $pos < $this->length ? $this->input[$pos] : '';
    }

    private function advance(): string
    {
        $char = $this->current();
        $this->position++;
        $this->column++;
        return $char;
    }

    private function makeToken(TokenType $type, string $value, mixed $literal): Token
    {
        return new Token(
            $type,
            $value,
            $literal,
            $this->line,
            $this->column,
            $this->position,
        );
    }

    private function makeSingleCharToken(TokenType $type): Token
    {
        $value = $this->advance();
        return $this->makeToken($type, $value, $value);
    }

    private function scanWhitespace(): Token
    {
        $start = $this->position;
        $startColumn = $this->column;

        while ($this->current() === ' ' or $this->current() === "\t") {
            $this->advance();
        }

        $value = \substr($this->input, $start, $this->position - $start);
        return new Token(
            TokenType::Whitespace,
            $value,
            null,
            $this->line,
            $startColumn,
            $start,
        );
    }

    private function scanNewline(): Token
    {
        $start = $this->position;
        $startColumn = $this->column;
        $char = $this->advance();

        // Handle \r\n
        if ($char === "\r" and $this->current() === "\n") {
            $this->advance();
        }

        $this->line++;
        $this->column = 1;

        return new Token(
            TokenType::Newline,
            "\n",
            null,
            $this->line - 1,
            $startColumn,
            $start,
        );
    }

    private function scanComment(): Token
    {
        $start = $this->position;
        $startColumn = $this->column;

        $this->advance(); // Skip '#'

        $commentStart = $this->position;

        while ($this->current() !== '' and $this->current() !== "\n" and $this->current() !== "\r") {
            $this->advance();
        }

        $value = \substr($this->input, $commentStart, $this->position - $commentStart);

        return new Token(
            TokenType::Comment,
            $value,
            $value,
            $this->line,
            $startColumn,
            $start,
        );
    }

    private function scanString(string $quote): Token
    {
        $start = $this->position;
        $startColumn = $this->column;
        $startLine = $this->line;

        $this->advance(); // Skip opening quote

        // Check for multiline string (triple quotes)
        if ($this->current() === $quote and $this->peek() === $quote) {
            $this->advance();
            $this->advance();
            return $this->scanMultilineString($quote, $start, $startColumn, $startLine);
        }

        $value = '';
        $isBasic = $quote === '"';

        while ($this->current() !== '' and $this->current() !== $quote) {
            if ($this->current() === "\n" or $this->current() === "\r") {
                throw new SyntaxException("Newline in string at line {$this->line}, column {$this->column}");
            }

            if ($isBasic and $this->current() === '\\') {
                $value .= $this->scanEscapeSequence();
            } else {
                $value .= $this->advance();
            }
        }

        if ($this->current() !== $quote) {
            throw new SyntaxException("Unterminated string at line {$this->line}, column {$this->column}");
        }

        $this->advance(); // Skip closing quote

        return new Token(
            TokenType::String,
            \substr($this->input, $start, $this->position - $start),
            $value,
            $startLine,
            $startColumn,
            $start,
        );
    }

    private function scanMultilineString(string $quote, int $start, int $startColumn, int $startLine): Token
    {
        $value = '';
        $isBasic = $quote === '"';

        // Skip first newline if present
        if ($this->current() === "\n") {
            $this->advance();
            $this->line++;
            $this->column = 1;
        } elseif ($this->current() === "\r" and $this->peek() === "\n") {
            $this->advance();
            $this->advance();
            $this->line++;
            $this->column = 1;
        }

        $closed = false;
        while ($this->current() !== '') {
            // Check for closing triple quotes (with up to 2 extra quotes as content)
            if ($this->current() === $quote and $this->peek() === $quote and $this->peek(2) === $quote) {
                // Count consecutive quotes
                $quoteCount = 0;
                $pos = $this->position;
                while ($pos < $this->length and $this->input[$pos] === $quote) {
                    $quoteCount++;
                    $pos++;
                }

                if ($quoteCount >= 6) {
                    throw new SyntaxException("Too many consecutive quotes at line {$this->line}, column {$this->column}");
                }

                // Extra quotes (1-2) before the closing """ are part of the content
                $extraQuotes = $quoteCount - 3;
                for ($i = 0; $i < $extraQuotes; $i++) {
                    $value .= $this->advance();
                }

                // Consume closing """
                $this->advance();
                $this->advance();
                $this->advance();
                $closed = true;
                break;
            }

            if ($this->current() === "\n") {
                $value .= $this->advance();
                $this->line++;
                $this->column = 1;
            } elseif ($this->current() === "\r" and $this->peek() === "\n") {
                $this->advance();
                $value .= $this->advance();
                $this->line++;
                $this->column = 1;
            } elseif ($isBasic and $this->current() === '\\') {
                // Line ending backslash in multiline basic strings
                // Backslash can be followed by optional whitespace then newline
                if ($this->isLineEndingBackslash()) {
                    $this->advance(); // Skip backslash
                    $this->scanLineEndingBackslash();
                    continue;
                }
                $value .= $this->scanEscapeSequence();
            } else {
                $value .= $this->advance();
            }
        }

        if (!$closed) {
            throw new SyntaxException("Unterminated multiline string starting at line {$startLine}, column {$startColumn}");
        }

        return new Token(
            TokenType::MultilineString,
            \substr($this->input, $start, $this->position - $start),
            $value,
            $startLine,
            $startColumn,
            $start,
        );
    }

    private function scanEscapeSequence(): string
    {
        $this->advance(); // Skip backslash
        $char = $this->advance(); // Consume escape character

        return match ($char) {
            'b' => "\x08",
            't' => "\t",
            'n' => "\n",
            'f' => "\f",
            'r' => "\r",
            'e' => "\x1B",
            '"' => '"',
            '\\' => '\\',
            'x' => $this->scanHexEscape(),
            'u' => $this->scanUnicodeEscape(4),
            'U' => $this->scanUnicodeEscape(8),
            default => throw new SyntaxException("Invalid escape sequence '\\{$char}' at line {$this->line}, column {$this->column}"),
        };
    }

    private function scanUnicodeEscape(int $length): string
    {
        $hex = '';
        for ($i = 0; $i < $length; $i++) {
            if (!\ctype_xdigit($this->current())) {
                throw new SyntaxException("Invalid unicode escape at line {$this->line}, column {$this->column}");
            }
            $hex .= $this->advance();
        }

        $codepoint = \hexdec($hex);
        return \mb_chr($codepoint, 'UTF-8');
    }

    private function scanHexEscape(): string
    {
        $hex = '';
        for ($i = 0; $i < 2; $i++) {
            if (!\ctype_xdigit($this->current())) {
                throw new SyntaxException("Invalid hex escape at line {$this->line}, column {$this->column}");
            }
            $hex .= $this->advance();
        }

        $codepoint = \hexdec($hex);
        return \mb_chr($codepoint, 'UTF-8');
    }

    /**
     * Checks if the current backslash starts a line-ending escape.
     * A line-ending backslash may be followed by optional whitespace before the newline.
     */
    private function isLineEndingBackslash(): bool
    {
        $offset = 1; // Start after the backslash
        while (true) {
            $ch = $this->peek($offset);
            if ($ch === "\n" or $ch === "\r") {
                return true;
            }
            if ($ch === ' ' or $ch === "\t") {
                $offset++;
                continue;
            }
            return false; // includes $ch === '' (end of input)
        }
    }

    private function scanLineEndingBackslash(): void
    {
        // Skip optional whitespace before the newline
        while ($this->current() === ' ' or $this->current() === "\t") {
            $this->advance();
        }

        // Skip newline
        if ($this->current() === "\r" and $this->peek() === "\n") {
            $this->advance();
        }
        if ($this->current() === "\n") {
            $this->advance();
            $this->line++;
            $this->column = 1;
        }

        // Skip whitespace after newline
        while ($this->current() === ' ' or $this->current() === "\t" or $this->current() === "\n" or $this->current() === "\r") {
            if ($this->current() === "\n") {
                $this->advance();
                $this->line++;
                $this->column = 1;
            } elseif ($this->current() === "\r" and $this->peek() === "\n") {
                $this->advance();
                $this->advance();
                $this->line++;
                $this->column = 1;
            } else {
                $this->advance();
            }
        }
    }

    private function scanValueOrKey(): Token
    {
        $start = $this->position;
        $startColumn = $this->column;
        $char = $this->current();

        // Try to scan as datetime first
        if (\ctype_digit($char)) {
            $saved = [$this->position, $this->line, $this->column];

            try {
                return $this->scanDateTimeOrNumber();
            } catch (SyntaxException $e) {
                // If it's an invalid date/time error, re-throw it
                if (\str_contains($e->getMessage(), "Invalid date '") or \str_contains($e->getMessage(), 'Invalid time')) {
                    throw $e;
                }

                // Otherwise restore position and try number
                [$this->position, $this->line, $this->column] = $saved;
            }
        }

        // Boolean
        if ($this->peek(0) === 't' and \substr($this->input, $this->position, 4) === 'true') {
            $this->position += 4;
            $this->column += 4;
            return new Token(TokenType::Boolean, 'true', true, $this->line, $startColumn, $start);
        }

        if ($this->peek(0) === 'f' and \substr($this->input, $this->position, 5) === 'false') {
            $this->position += 5;
            $this->column += 5;
            return new Token(TokenType::Boolean, 'false', false, $this->line, $startColumn, $start);
        }

        // Float special values
        if (\substr($this->input, $this->position, 3) === 'inf' or \substr($this->input, $this->position, 4) === '+inf') {
            $length = $this->current() === '+' ? 4 : 3;
            $value = \substr($this->input, $this->position, $length);
            $this->position += $length;
            $this->column += $length;
            return new Token(TokenType::Float, $value, INF, $this->line, $startColumn, $start);
        }

        if (\substr($this->input, $this->position, 4) === '-inf') {
            $this->position += 4;
            $this->column += 4;
            return new Token(TokenType::Float, '-inf', -INF, $this->line, $startColumn, $start);
        }

        if (\substr($this->input, $this->position, 3) === 'nan' or \substr($this->input, $this->position, 4) === '+nan' or \substr($this->input, $this->position, 4) === '-nan') {
            $length = ($this->current() === '+' or $this->current() === '-') ? 4 : 3;
            $value = \substr($this->input, $this->position, $length);
            $this->position += $length;
            $this->column += $length;
            return new Token(TokenType::Float, $value, NAN, $this->line, $startColumn, $start);
        }

        // Number (integer or float)
        if (\ctype_digit($char) or $char === '+' or $char === '-') {
            return $this->scanNumber();
        }

        // Bare key
        return $this->scanBareKey();
    }

    private function scanDateTimeOrNumber(): Token
    {
        $start = $this->position;
        $startColumn = $this->column;

        // Check for local time (HH:MM:SS) - look ahead for pattern like "07:32:00"
        if (\ctype_digit($this->current())
            and \ctype_digit($this->peek(1))
            and $this->peek(2) === ':') {
            return $this->scanLocalTime($start, $startColumn);
        }

        // Scan yyyy-mm-dd pattern
        $dateStr = '';

        for ($i = 0; $i < 4; $i++) {
            if (!\ctype_digit($this->current())) {
                throw new SyntaxException('Not a datetime');
            }
            $dateStr .= $this->advance();
        }

        if ($this->current() !== '-') {
            throw new SyntaxException('Not a datetime');
        }
        $dateStr .= $this->advance();

        for ($i = 0; $i < 2; $i++) {
            if (!\ctype_digit($this->current())) {
                throw new SyntaxException('Not a datetime');
            }
            $dateStr .= $this->advance();
        }

        if ($this->current() !== '-') {
            throw new SyntaxException('Not a datetime');
        }
        $dateStr .= $this->advance();

        for ($i = 0; $i < 2; $i++) {
            if (!\ctype_digit($this->current())) {
                throw new SyntaxException('Not a datetime');
            }
            $dateStr .= $this->advance();
        }

        // Local date without time
        // Space is a datetime separator only if followed by a digit (start of time)
        $isTimeSeparator = ($this->current() === 'T' or $this->current() === 't')
            || ($this->current() === ' ' and \ctype_digit($this->peek()));
        if (!$isTimeSeparator) {
            $value = \substr($this->input, $start, $this->position - $start);
            $datetime = \DateTimeImmutable::createFromFormat('Y-m-d', $value, new \DateTimeZone('UTC'));

            // Validate the date is actually valid
            if ($datetime === false or $datetime->format('Y-m-d') !== $value) {
                throw new SyntaxException("Invalid date '{$value}' at line {$this->line}, column {$startColumn}");
            }

            // Set time to 00:00:00 for local date
            $datetime = $datetime->setTime(0, 0, 0, 0);

            return new Token(
                TokenType::Datetime,
                $value,
                $datetime,
                $this->line,
                $startColumn,
                $start,
            );
        }

        // Continue with time part
        $dateStr .= $this->advance(); // T or space

        // Scan time
        return $this->scanDateTime($dateStr, $start, $startColumn);
    }

    private function scanDateTime(string $dateStr, int $start, int $startColumn): Token
    {
        // hh:mm
        for ($i = 0; $i < 2; $i++) {
            if (!\ctype_digit($this->current())) {
                throw new SyntaxException("Invalid datetime at line {$this->line}, column {$this->column}");
            }
            $dateStr .= $this->advance();
        }

        if ($this->current() !== ':') {
            throw new SyntaxException("Invalid datetime at line {$this->line}, column {$this->column}");
        }
        $dateStr .= $this->advance();

        for ($i = 0; $i < 2; $i++) {
            if (!\ctype_digit($this->current())) {
                throw new SyntaxException("Invalid datetime at line {$this->line}, column {$this->column}");
            }
            $dateStr .= $this->advance();
        }

        // Optional seconds (TOML 1.1)
        $hasSeconds = false;
        if ($this->current() === ':') {
            $hasSeconds = true;
            $dateStr .= $this->advance();

            for ($i = 0; $i < 2; $i++) {
                if (!\ctype_digit($this->current())) {
                    throw new SyntaxException("Invalid datetime at line {$this->line}, column {$this->column}");
                }
                $dateStr .= $this->advance();
            }

            // Optional fractional seconds
            if ($this->current() === '.') {
                $dateStr .= $this->advance();
                while (\ctype_digit($this->current())) {
                    $dateStr .= $this->advance();
                }
            }
        }

        // Optional timezone
        if ($this->current() === 'Z' or $this->current() === 'z') {
            $dateStr .= $this->advance();
        } elseif ($this->current() === '+' or $this->current() === '-') {
            $dateStr .= $this->advance();

            for ($i = 0; $i < 2; $i++) {
                if (!\ctype_digit($this->current())) {
                    throw new SyntaxException("Invalid datetime offset at line {$this->line}, column {$this->column}");
                }
                $dateStr .= $this->advance();
            }

            if ($this->current() === ':') {
                $dateStr .= $this->advance();
            }

            for ($i = 0; $i < 2; $i++) {
                if (!\ctype_digit($this->current())) {
                    throw new SyntaxException("Invalid datetime offset at line {$this->line}, column {$this->column}");
                }
                $dateStr .= $this->advance();
            }
        }

        $value = \substr($this->input, $start, $this->position - $start);

        // Inject :00 seconds for DateTimeImmutable parsing when seconds are omitted
        $parseValue = $hasSeconds ? $value : \preg_replace(
            '/^(\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2})/',
            '$1:00',
            $value,
        );

        $datetime = \DateTimeImmutable::createFromFormat(DATE_RFC3339_EXTENDED, $parseValue)
            ?: \DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s', $parseValue)
            ?: \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $parseValue)
            ?: \DateTimeImmutable::createFromFormat('Y-m-d\TH:i:sP', $parseValue)
            ?: \DateTimeImmutable::createFromFormat('Y-m-d H:i:sP', $parseValue);

        return new Token(
            TokenType::Datetime,
            $value,
            $datetime ?: null,
            $this->line,
            $startColumn,
            $start,
        );
    }

    private function scanNumber(): Token
    {
        $start = $this->position;
        $startColumn = $this->column;
        $value = '';

        // Sign
        if ($this->current() === '+' or $this->current() === '-') {
            $value .= $this->advance();
        }

        // Hex, octal, binary
        if ($this->current() === '0' and ($this->peek() === 'x' or $this->peek() === 'o' or $this->peek() === 'b')) {
            $value .= $this->advance();
            $value .= $this->advance();

            while (\ctype_xdigit($this->current()) or $this->current() === '_') {
                if ($this->current() !== '_') {
                    $value .= $this->advance();
                } else {
                    $this->advance(); // Skip underscore
                }
            }

            $raw = \substr($this->input, $start, $this->position - $start);
            $cleanValue = \str_replace('_', '', $value);

            $literal = match ($value[1]) {
                'x' => \intval(\substr($cleanValue, 2), 16),
                'o' => \intval(\substr($cleanValue, 2), 8),
                'b' => \intval(\substr($cleanValue, 2), 2),
                default => 0,
            };

            return new Token(
                TokenType::Integer,
                $raw,
                $literal,
                $this->line,
                $startColumn,
                $start,
            );
        }

        // Decimal integer or float
        $isFloat = false;

        while (\ctype_digit($this->current()) or $this->current() === '_' or $this->current() === '.' or $this->current() === 'e' or $this->current() === 'E') {
            if ($this->current() === '.') {
                // Check if next is digit (not another dot or end)
                if (!\ctype_digit($this->peek())) {
                    break;
                }
                $isFloat = true;
                $value .= $this->advance();
            } elseif ($this->current() === 'e' or $this->current() === 'E') {
                $isFloat = true;
                $value .= $this->advance();

                if ($this->current() === '+' or $this->current() === '-') {
                    $value .= $this->advance();
                }
            } elseif ($this->current() !== '_') {
                $value .= $this->advance();
            } else {
                $this->advance(); // Skip underscore
            }
        }

        $raw = \substr($this->input, $start, $this->position - $start);
        $cleanValue = \str_replace('_', '', $value);

        if ($isFloat) {
            return new Token(
                TokenType::Float,
                $raw,
                (float) $cleanValue,
                $this->line,
                $startColumn,
                $start,
            );
        }

        return new Token(
            TokenType::Integer,
            $raw,
            (int) $cleanValue,
            $this->line,
            $startColumn,
            $start,
        );
    }

    private function scanLocalTime(int $start, int $startColumn): Token
    {
        $value = '';

        // hh:mm
        for ($i = 0; $i < 2; $i++) {
            if (!\ctype_digit($this->current())) {
                throw new SyntaxException("Invalid time at line {$this->line}, column {$this->column}");
            }
            $value .= $this->advance();
        }

        if ($this->current() !== ':') {
            throw new SyntaxException("Invalid time at line {$this->line}, column {$this->column}");
        }
        $value .= $this->advance();

        for ($i = 0; $i < 2; $i++) {
            if (!\ctype_digit($this->current())) {
                throw new SyntaxException("Invalid time at line {$this->line}, column {$this->column}");
            }
            $value .= $this->advance();
        }

        // Optional seconds (TOML 1.1)
        if ($this->current() === ':') {
            $value .= $this->advance();

            for ($i = 0; $i < 2; $i++) {
                if (!\ctype_digit($this->current())) {
                    throw new SyntaxException("Invalid time at line {$this->line}, column {$this->column}");
                }
                $value .= $this->advance();
            }

            // Optional fractional seconds
            if ($this->current() === '.') {
                $value .= $this->advance();
                while (\ctype_digit($this->current())) {
                    $value .= $this->advance();
                }
            }
        }

        return new Token(
            TokenType::Datetime,
            $value,
            $value,
            $this->line,
            $startColumn,
            $start,
        );
    }

    private function scanBareKey(): Token
    {
        $start = $this->position;
        $startColumn = $this->column;
        $value = '';

        while (\ctype_alnum($this->current()) or $this->current() === '_' or $this->current() === '-') {
            $value .= $this->advance();
        }

        if ($value === '') {
            throw new SyntaxException("Unexpected character '{$this->current()}' at line {$this->line}, column {$this->column}");
        }

        return new Token(
            TokenType::BareKey,
            $value,
            $value,
            $this->line,
            $startColumn,
            $start,
        );
    }
}
