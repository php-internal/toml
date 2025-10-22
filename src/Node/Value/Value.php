<?php

declare(strict_types=1);

namespace Internal\Toml\Node\Value;

use Internal\Toml\Node\Node;

/**
 * Base class for all value types in TOML.
 */
abstract class Value extends Node
{
    abstract public function toPhpValue(): mixed;
}
