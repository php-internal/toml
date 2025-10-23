<?php

declare(strict_types=1);

namespace Internal\Toml\Node;

/**
 * Base class for all AST nodes.
 */
abstract class Node implements \Stringable
{
    public function __construct(
        public readonly Position $position,
    ) {}
}
