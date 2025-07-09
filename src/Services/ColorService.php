<?php

declare(strict_types=1);

namespace Adeliom\HorizonTools\Services;

class ColorService
{
    public static function adjustBrightness(string $hexCode, int|float $adjustPercent): string
    {
        $hexCode = ltrim($hexCode, '#');

        if (strlen($hexCode) == 3) {
            $hexCode = $hexCode[0] . $hexCode[0] . $hexCode[1] . $hexCode[1] . $hexCode[2] . $hexCode[2];
        }

        $hexCode = array_map('hexdec', str_split($hexCode, 2));

        foreach ($hexCode as &$color) {
            $adjustableLimit = $adjustPercent < 0 ? $color : 255 - $color;
            $adjustAmount = intval(ceil($adjustableLimit * $adjustPercent));

            $color = str_pad(dechex($color + $adjustAmount), 2, '0', STR_PAD_LEFT);
        }

        return '#' . implode($hexCode);
    }
}
