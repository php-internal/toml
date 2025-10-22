<?php

declare(strict_types=1);

namespace Internal\Toml\Node;

use Internal\Toml\Node\Value\Value;

/**
 * Universal node for all document entries.
 *
 * Variants:
 * - key=null, value=null, comment="text" → standalone/multiline comment
 * - key=Key, value=Value, comment=null → key-value pair
 * - key=Key, value=Value, comment="text" → key-value with inline comment
 */
final class Entry extends Node
{
    public function __construct(
        public readonly ?Key $key,
        public readonly ?Value $value,
        public readonly ?string $comment,
        Position $position,
    ) {
        parent::__construct($position);
    }

    public function isComment(): bool
    {
        return $this->key === null and $this->value === null and $this->comment !== null;
    }

    public function isKeyValue(): bool
    {
        return $this->key !== null and $this->value !== null;
    }

    public function hasInlineComment(): bool
    {
        return $this->isKeyValue() and $this->comment !== null;
    }

    public function isEmpty(): bool
    {
        return $this->key === null and $this->value === null and $this->comment === null;
    }
}
