<?php

declare(strict_types=1);

namespace Adeliom\HorizonTools\Services;

class StringService
{
    public static function truncate(
        string $string,
        int $length = 100,
        string $suffix = '…',
        bool $takeSuffixLengthIntoAccount = true
    ): string {
        if (mb_strlen($string) <= $length) {
            return $string;
        }

        if ($takeSuffixLengthIntoAccount) {
            $length -= mb_strlen($suffix);

            if ($length < 0) {
                $length = 0; // Ensure length is not negative
            }
        }

        return mb_substr($string, 0, $length) . $suffix;
    }

    public static function singularOrPlural(int $count, string $singular = 'élément', string $plural = 'éléments'): string
    {
        return $count === 1 ? $singular : $plural;
    }

    public static function toCamelCase(string $string): string
    {
        return str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $string)));
    }
}
