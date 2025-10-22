<?php

declare(strict_types=1);

namespace Internal\Toml\Parser;

/**
 * Represents the type of a token.
 *
 * @internal
 */
enum TokenType: string
{
    case Equals = '=';
    case Dot = '.';
    case Comma = ',';
    case Newline = '\n';
    case Eof = 'EOF';
    case LeftBracket = '[';
    case RightBracket = ']';
    case LeftBrace = '{';
    case RightBrace = '}';
    case String = 'STRING';
    case MultilineString = 'ML_STRING';
    case Integer = 'INTEGER';
    case Float = 'FLOAT';
    case Boolean = 'BOOLEAN';
    case Datetime = 'DATETIME';
    case BareKey = 'BARE_KEY';
    case QuotedKey = 'QUOTED_KEY';
    case Comment = 'COMMENT';
    case Whitespace = 'WHITESPACE';
}
