<?php

declare(strict_types=1);

namespace Internal\Toml\Node\Value;

/**
 * Represents the type of a string value.
 */
enum StringType
{
    case Basic;              // "string"
    case Literal;            // 'string'
    case MultilineBasic;     // """string"""
    case MultilineLiteral;   // '''string'''
}
