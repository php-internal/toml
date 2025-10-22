<?php

declare(strict_types=1);

namespace Internal\Toml\Node;

/**
 * Interface for nodes that can contain multiple entries.
 */
interface MultiLineNode
{
    /**
     * @return list<Entry>
     */
    public function getEntries(): array;
}
