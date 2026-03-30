#!/usr/bin/env php
<?php

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

use Internal\Toml\Node\Document;
use Internal\Toml\Node\Entry;
use Internal\Toml\Node\Key;
use Internal\Toml\Node\Table;
use Internal\Toml\Node\TableArray;
use Internal\Toml\Node\Value\ArrayValue;
use Internal\Toml\Node\Value\BooleanValue;
use Internal\Toml\Node\Value\DateTimeType;
use Internal\Toml\Node\Value\DateTimeValue;
use Internal\Toml\Node\Value\FloatValue;
use Internal\Toml\Node\Value\InlineTableValue;
use Internal\Toml\Node\Value\IntegerValue;
use Internal\Toml\Node\Value\LocalTimeValue;
use Internal\Toml\Node\Value\StringValue;
use Internal\Toml\Node\Value\Value;
use Internal\Toml\Toml;

$input = \file_get_contents('php://stdin');

try {
    $document = Toml::parse($input);
    $result = documentToTagged($document);
    echo \json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION) . "\n";
    exit(0);
} catch (\Throwable $e) {
    \fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}

function documentToTagged(Document $doc): \stdClass
{
    $result = new \stdClass();

    foreach ($doc->nodes as $node) {
        match (true) {
            $node instanceof Entry && $node->isKeyValue() => addTaggedEntry($result, $node->key, $node->value),
            $node instanceof Table => addTaggedTable($result, $node),
            $node instanceof TableArray => addTaggedTableArray($result, $node),
            default => null,
        };
    }

    return $result;
}

function addTaggedEntry(\stdClass $result, Key $key, Value $value): void
{
    $tagged = valueToTagged($value);

    if ($key->isSimple()) {
        $result->{$key->getFirstSegment()} = $tagged;
        return;
    }

    $current = $result;
    $segments = $key->segments;
    $lastIndex = \count($segments) - 1;

    foreach ($segments as $i => $segment) {
        if ($i === $lastIndex) {
            $current->$segment = $tagged;
        } else {
            if (!isset($current->$segment)) {
                $current->$segment = new \stdClass();
            }
            $current = $current->$segment;
        }
    }
}

function addTaggedTable(\stdClass $result, Table $table): void
{
    $current = $result;

    foreach ($table->name->segments as $segment) {
        if (!isset($current->$segment)) {
            $current->$segment = new \stdClass();
        }
        $val = $current->$segment;
        if (\is_array($val) && $val !== []) {
            // Navigate into last element of array-of-tables
            $current = $val[\count($val) - 1];
        } else {
            $current = $val;
        }
    }

    foreach ($table->entries as $entry) {
        if ($entry->isKeyValue() && $entry->key !== null && $entry->value !== null) {
            addTaggedEntry($current, $entry->key, $entry->value);
        }
    }
}

function addTaggedTableArray(\stdClass $result, TableArray $tableArray): void
{
    $current = $result;
    $segments = $tableArray->name->segments;
    $lastIndex = \count($segments) - 1;

    foreach ($segments as $i => $segment) {
        if ($i === $lastIndex) {
            if (!isset($current->$segment)) {
                $current->$segment = [];
            }
            $newEntry = new \stdClass();
            $current->$segment[] = $newEntry;
            $current = $newEntry;
        } else {
            if (!isset($current->$segment)) {
                $current->$segment = new \stdClass();
            }
            $val = $current->$segment;
            if (\is_array($val) && $val !== []) {
                $current = $val[\count($val) - 1];
            } else {
                $current = $val;
            }
        }
    }

    foreach ($tableArray->entries as $entry) {
        if ($entry->isKeyValue() && $entry->key !== null && $entry->value !== null) {
            addTaggedEntry($current, $entry->key, $entry->value);
        }
    }
}

function valueToTagged(Value $value): mixed
{
    return match (true) {
        $value instanceof StringValue => ['type' => 'string', 'value' => $value->value],
        $value instanceof IntegerValue => ['type' => 'integer', 'value' => (string) $value->value],
        $value instanceof FloatValue => floatToTagged($value),
        $value instanceof BooleanValue => ['type' => 'bool', 'value' => $value->value ? 'true' : 'false'],
        $value instanceof DateTimeValue => datetimeToTagged($value),
        $value instanceof LocalTimeValue => ['type' => 'time-local', 'value' => $value->value],
        $value instanceof ArrayValue => \array_map(fn(Value $el): mixed => valueToTagged($el), $value->elements),
        $value instanceof InlineTableValue => inlineTableToTagged($value),
        default => throw new \RuntimeException('Unknown value type: ' . $value::class),
    };
}

function floatToTagged(FloatValue $value): array
{
    $val = $value->value;

    if (\is_nan($val)) {
        $str = 'nan';
    } elseif (\is_infinite($val) && $val > 0) {
        $str = 'inf';
    } elseif (\is_infinite($val)) {
        $str = '-inf';
    } else {
        // Ensure float representation always has a decimal point
        $str = (string) $val;
        if (!\str_contains($str, '.') && !\str_contains($str, 'E') && !\str_contains($str, 'e')) {
            $str .= '.0';
        }
    }

    return ['type' => 'float', 'value' => $str];
}

function datetimeToTagged(DateTimeValue $value): array
{
    $type = match ($value->type) {
        DateTimeType::OffsetDatetime => 'datetime',
        DateTimeType::LocalDatetime => 'datetime-local',
        DateTimeType::LocalDate => 'date-local',
    };

    // Normalize raw: replace space separator with T
    $raw = $value->raw;
    $raw = \preg_replace('/^(\d{4}-\d{2}-\d{2})[ t]/', '$1T', $raw);

    return ['type' => $type, 'value' => $raw];
}

function inlineTableToTagged(InlineTableValue $value): \stdClass
{
    $result = new \stdClass();

    foreach ($value->pairs as $key => $val) {
        // Handle dotted keys within inline tables
        if (\str_contains((string) $key, '.')) {
            $segments = \explode('.', (string) $key);
            $current = $result;
            $lastIndex = \count($segments) - 1;

            foreach ($segments as $i => $segment) {
                if ($i === $lastIndex) {
                    $current->$segment = valueToTagged($val);
                } else {
                    if (!isset($current->$segment)) {
                        $current->$segment = new \stdClass();
                    }
                    $current = $current->$segment;
                }
            }
        } else {
            $result->{(string) $key} = valueToTagged($val);
        }
    }

    return $result;
}
