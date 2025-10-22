<?php

declare(strict_types=1);

namespace Internal\Toml\Node;

/**
 * Represents the type of a key segment.
 */
enum KeyType
{
    case Bare;      // A-Za-z0-9_-
    case Quoted;    // "quoted key"
}
