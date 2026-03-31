#!/usr/bin/env php
<?php

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

use Internal\Toml\Node\Position;
use Internal\Toml\Node\Value\DateTimeType;
use Internal\Toml\Node\Value\DateTimeValue;
use Internal\Toml\Node\Value\LocalTimeValue;
use Internal\Toml\Toml;

$input = \file_get_contents('php://stdin');

try {
    $json = \json_decode($input, true, 512, JSON_THROW_ON_ERROR);
    $data = taggedToPhp($json);
    echo (string) Toml::encode($data);
    exit(0);
} catch (\Throwable $e) {
    \fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}

function taggedToPhp(mixed $value): mixed
{
    if (!\is_array($value)) {
        throw new \RuntimeException('Unexpected non-array value');
    }

    // Tagged value: {"type": "...", "value": "..."}
    if (isset($value['type']) && isset($value['value']) && \count($value) === 2) {
        return convertTaggedValue($value['type'], $value['value']);
    }

    // Array of tagged values (TOML array)
    if (\array_is_list($value)) {
        return \array_map(static fn(mixed $item): mixed => taggedToPhp($item), $value);
    }

    // Table: recursively convert
    $result = [];
    foreach ($value as $key => $val) {
        $result[$key] = taggedToPhp($val);
    }

    return $result;
}

function convertTaggedValue(string $type, string $value): mixed
{
    return match ($type) {
        'string' => $value,
        'integer' => (int) $value,
        'float' => convertFloat($value),
        'bool' => $value === 'true',
        'datetime' => new DateTimeValue(
            new \DateTimeImmutable($value), DateTimeType::OffsetDatetime, $value, new Position(0, 0, 0),
        ),
        'datetime-local' => new DateTimeValue(
            new \DateTimeImmutable($value), DateTimeType::LocalDatetime, $value, new Position(0, 0, 0),
        ),
        'date-local' => new DateTimeValue(
            new \DateTimeImmutable($value), DateTimeType::LocalDate, $value, new Position(0, 0, 0),
        ),
        'time-local' => new LocalTimeValue($value, new Position(0, 0, 0)),
        default => throw new \RuntimeException("Unknown type: {$type}"),
    };
}

function convertFloat(string $value): float
{
    return match ($value) {
        'inf', '+inf' => INF,
        '-inf' => -INF,
        'nan', '+nan', '-nan' => NAN,
        default => (float) $value,
    };
}
