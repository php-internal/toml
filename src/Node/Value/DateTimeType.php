<?php

declare(strict_types=1);

namespace Internal\Toml\Node\Value;

/**
 * Represents the type of a date-time value.
 */
enum DateTimeType
{
    case OffsetDatetime;   // 1979-05-27T07:32:00Z
    case LocalDatetime;    // 1979-05-27T07:32:00
    case LocalDate;        // 1979-05-27
}
