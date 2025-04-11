<?php

declare(strict_types=1);

namespace Adeliom\HorizonTools\Services;

use Illuminate\Support\Facades\Request;

class SeoService
{
    public const OBFUSCATE_ATTRIBUTE = 'data-href';

    public static function isRankMathActive(): bool
    {
        if (function_exists('is_plugin_active')) {
            return is_plugin_active('seo-by-rank-math/rank-math.php');
        }

        return false;
    }

    public static function getCurrentUrl(): ?string
    {
        return Request::fullUrl();
    }

    public static function getCurrentTitle(): ?string
    {
        return get_the_title();
    }

    public static function getHrefAttribute(?string $url = null, bool $obfuscate = false): ?string
    {
        if (null === $url) {
            return null;
        }

        if (!$obfuscate) {
            return sprintf('href="%s"', $url);
        }

        return sprintf('%s="%s"', self::OBFUSCATE_ATTRIBUTE, base64_encode($url));
    }
}
