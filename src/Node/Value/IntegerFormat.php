<?php

declare(strict_types=1);

namespace Internal\Toml\Node\Value;

/**
 * Represents the format of an integer value.
 */
enum IntegerFormat
{
    case Decimal;   // 42
    case Hex;       // 0xDEADBEEF
    case Octal;     // 0o755
    case Binary;    // 0b11010110
}
