<?php

namespace App\Support;

class Bytes
{
    /**
     * Parse a PHP ini size string (e.g. "2M", "512K", "1024") to bytes.
     * Returns 0 when the value is empty or not parseable.
     */
    public static function fromIni(string $value): int
    {
        $value = trim($value);

        if ($value === '') {
            return 0;
        }

        $unit = strtolower(substr($value, -1));
        $number = (int) $value;

        return match ($unit) {
            'g' => $number * 1024 * 1024 * 1024,
            'm' => $number * 1024 * 1024,
            'k' => $number * 1024,
            default => $number,
        };
    }
}
