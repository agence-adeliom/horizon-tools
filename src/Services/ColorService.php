<?php

declare(strict_types=1);

namespace Adeliom\HorizonTools\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;

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

    public static function getSiteMainColorFromIcon(bool $useCache = true): ?string
    {
        if ($useCache) {
            return Cache::remember('site_main_color_from_icon', 3600, function () {
                return self::getSiteMainColorFromIconLogic();
            });
        }

        return self::getSiteMainColorFromIconLogic();
    }

    public static function getSiteMainColorBrightnessCoefficient(): null|int|float
    {
        return Config::get('back-office.login.mainColorBrightnessCoefficient', 0);
    }

    private static function getSiteMainColorFromIconLogic(): ?string
    {
        $mainColor = null;
        $iconPath = null;
        $iconUrl = BackOfficeService::getBackOfficeIconUrl();

        if (null !== $iconUrl) {
            $faviconPath = parse_url($iconUrl, PHP_URL_PATH);
            $iconPath = rtrim(FileService::getPathToWpConfigFolder(), '/') . $faviconPath;

            if (!file_exists($iconPath)) {
                $iconPath = null;
            }
        }

        if (null !== $iconUrl || null !== $iconPath) {
            $mainColor =
                null !== $iconPath
                    ? ImageService::getMainColorFromImageByPath(imagePath: $iconPath)
                    : ImageService::getMainColorFromImageByUrl(imageUrl: $iconUrl);
        }

        if ($mainColor && ($coefficient = self::getSiteMainColorBrightnessCoefficient())) {
            $mainColor = self::adjustBrightness($mainColor, $coefficient);
        }

        return $mainColor;
    }
}
