# TOML for PHP

TOML v1.0.0 parser and encoder for PHP 8.1+. Parse TOML files into PHP arrays or encode PHP data structures back to TOML format.

## Installation

```bash
composer require internal/toml
```

## Quick Start

### Parsing TOML

```php
use Internal\Toml\Toml;

// Parse to PHP array
$data = Toml::parseToArray(<<<'TOML'
title = "TOML Example"
version = "1.0.0"

[database]
host = "localhost"
ports = [8000, 8001, 8002]
TOML);

// Result:
// [
//     'title' => 'TOML Example',
//     'version' => '1.0.0',
//     'database' => [
//         'host' => 'localhost',
//         'ports' => [8000, 8001, 8002],
//     ],
// ]
```

### Encoding to TOML

```php
use Internal\Toml\Toml;

$data = [
    'title' => 'Configuration',
    'database' => [
        'host' => 'localhost',
        'port' => 5432,
    ],
    'servers' => [
        ['name' => 'alpha', 'ip' => '10.0.0.1'],
        ['name' => 'beta', 'ip' => '10.0.0.2'],
    ],
];

$toml = (string) Toml::encode($data);

// Output:
// title = 'Configuration'
//
// [database]
// host = 'localhost'
// port = 5432
//
// [[servers]]
// name = 'alpha'
// ip = '10.0.0.1'
//
// [[servers]]
// name = 'beta'
// ip = '10.0.0.2'
```

### Working with AST

```php
use Internal\Toml\Toml;

// Parse to AST (Abstract Syntax Tree)
$document = Toml::parse('key = "value"');

// Access nodes
foreach ($document->nodes as $node) {
    // Work with Entry, Table, TableArray nodes
}

// Convert to array
$array = $document->toArray();

// Serialize back to TOML
$toml = (string) $document;
```

### Round-Trip Conversion

```php
// Parse → Modify → Encode
$document = Toml::parse($tomlString);
$data = $document->toArray();

// Modify data
$data['new_key'] = 'new_value';

// Encode back
$newToml = (string) Toml::encode($data);

// Perfect round-trip preservation
$parsed = Toml::parseToArray($newToml);
```

## Examples

### DateTime Support

```php
$data = [
    'created' => new DateTimeImmutable('1979-05-27T07:32:00Z'),
    'updated' => new DateTimeImmutable('2024-01-15T10:30:00+03:00'),
];

$toml = (string) Toml::encode($data);
// created = 1979-05-27T07:32:00Z
// updated = 2024-01-15T10:30:00+03:00
```

### JsonSerializable Support

```php
$object = new class implements JsonSerializable {
    public function jsonSerialize(): array {
        return ['name' => 'Example', 'value' => 123];
    }
};

$toml = (string) Toml::encode($object);
```

### Format Preservation

```php
// Original TOML with hex number
$toml = 'magic = 0xDEADBEEF';

$document = Toml::parse($toml);
echo (string) $document;
// Output: magic = 0xDEADBEEF
// ✅ Original format preserved!
```

## API

```php
// Parse TOML string to Document AST
Toml::parse(string $toml): Document

// Parse TOML string to PHP array
Toml::parseToArray(string $toml): array

// Encode PHP array or JsonSerializable to TOML
Toml::encode(array|JsonSerializable $data): Stringable
```

## What's supported

All TOML v1.0.0 features: strings (basic/literal/multiline), integers (decimal/hex/octal/binary), floats, booleans, datetime, arrays, tables, inline tables, dotted keys, comments.

The library preserves original formatting when doing round-trips (hex numbers stay hex, comments are kept, etc).

## Documentation

- [TOML v1.0.0 Specification](https://toml.io/en/v1.0.0)

## License

BSD-3-Clause
