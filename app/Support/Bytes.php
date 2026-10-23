<?php

namespace App\Support;

/**
 * File sizes for display. Laravel's Number::fileSize needs the intl
 * extension, which the server doesn't have.
 */
class Bytes
{
    public static function format(int|float $bytes, int $precision = 1): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;

        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return number_format($bytes, $i === 0 ? 0 : $precision).' '.$units[$i];
    }
}
