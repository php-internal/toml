<?php

declare(strict_types=1);

namespace Internal\Toml\Node\Value;

use Internal\Toml\Node\Position;

/**
 * Represents a string value in TOML.
 */
final class StringValue extends Value
{
    public function __construct(
        public readonly string $value,
        public readonly StringType $type,
        Position $position,
    ) {
        parent::__construct($position);
    }

    public function toPhpValue(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return match ($this->type) {
            StringType::Basic => '"' . $this->escapeBasicString($this->value) . '"',
            StringType::Literal => "'" . $this->value . "'",
            StringType::MultilineBasic => '"""' . "\n" . $this->escapeBasicString($this->value) . '"""',
            StringType::MultilineLiteral => "'''" . "\n" . $this->value . "'''",
        };
    }

    private function escapeBasicString(string $value): string
    {
        return \strtr($value, [
            "\x08" => '\b',
            "\t" => '\t',
            "\n" => '\n',
            "\f" => '\f',
            "\r" => '\r',
            "\x1B" => '\u001B',
            '"' => '\"',
            '\\' => '\\\\',
        ]);
    }
}
