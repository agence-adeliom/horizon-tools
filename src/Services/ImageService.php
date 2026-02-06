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
        // Check if URL returns something
        try {
            if (empty($imageUrl) || !filter_var($imageUrl, FILTER_VALIDATE_URL)) {
                return null;
            }

            // Test rapide avec headers
            $headers = @get_headers($imageUrl, true);

            if (!$headers || !str_contains($headers[0], '200')) {
                return null;
            }

            $contentType = $headers['Content-Type'] ?? '';
            if (is_array($contentType)) {
                $contentType = $contentType[0];
            }

            if (!str_starts_with($contentType, 'image/')) {
                return null;
            }

            return self::getMainColorFromPalette(palette: Palette::fromUrl($imageUrl));
        } catch (\Exception $e) {
            \Log::warning('Color extraction failed', ['url' => $imageUrl]);
            return null;
        }
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
