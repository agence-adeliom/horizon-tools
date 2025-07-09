<?php

declare(strict_types=1);

namespace Adeliom\HorizonTools\Services;

use League\ColorExtractor\Color;
use League\ColorExtractor\ColorExtractor;
use League\ColorExtractor\Palette;

class ImageService
{
    public static function getMainColorFromImageByUrl(string $imageUrl): ?string
    {
        return self::getMainColorFromPalette(palette: Palette::fromUrl($imageUrl));
    }

    public static function getMainColorFromImageByPath(string $imagePath): ?string
    {
        return self::getMainColorFromPalette(palette: Palette::fromFilename($imagePath));
    }

    private static function getMainColorFromPalette(Palette $palette)
    {
        $mainColor = null;

        $extractor = new ColorExtractor($palette);

        $colors = $extractor->extract();

        if (!empty($colors[0])) {
            $mainColor = Color::fromIntToHex($colors[0]);
        }

        return $mainColor;
    }
}
