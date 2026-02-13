<?php

declare(strict_types=1);

namespace Adeliom\HorizonTools\Services;

use Carbon\Carbon;

class DateService
{
    public const ACF_DISPLAY_FORMAT = 'd/m/Y H:i';
    public const ACF_RETURN_FORMAT = 'Y-m-d H:i:s';

    public static function getPrettyDate(?string $date): ?string
    {
        if (null === $date) {
            return null;
        }

        $carbon = Carbon::createFromFormat('Y-m-d H:i:s', $date, 'Europe/Paris')->locale('fr_FR');

        $suffix = match (true) {
            $carbon->day === 1 => 'er',
            default => '',
        };

        return sprintf('%s%s %s %s', $carbon->day, $suffix, $carbon->monthName, $carbon->year);
    }
}
