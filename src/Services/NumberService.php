<?php

declare(strict_types=1);

namespace Adeliom\HorizonTools\Services;

class NumberService
{
    public static function formatPrice(
        string|int|float $price,
        int $decimals = 0,
        string $decimalsSeparator = ',',
        string $thousandsSeparator = ' ',
        string $unitSeparator = ' ',
        string $unit = '€'
    ): ?string {
        $value = null;

        if (is_string($price)) {
            if (!is_numeric($price)) {
                return null;
            }

            $price = (float) $price;
        }

        if ($price) {
            $price = number_format(
                num: $price,
                decimals: $decimals,
                decimal_separator: $decimalsSeparator,
                thousands_separator: $thousandsSeparator
            );
            $value = $price;

            if ($unit) {
                $value = sprintf('%s%s%s', $price, $unitSeparator, $unit);
            }
        }

        return $value;
    }
}
