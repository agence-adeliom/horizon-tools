<?php

declare(strict_types=1);

namespace Adeliom\HorizonTools\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Request;

class SeoService
{
    public const OBFUSCATE_ATTRIBUTE = 'data-obfuscated-href';

    public static function isRankMathActive(): bool
    {
        if (function_exists('is_plugin_active')) {
            return is_plugin_active('seo-by-rank-math/rank-math.php');
        }

        return false;
    }

    public static function isSEOPressActive(): bool
    {
        if (function_exists('is_plugin_active')) {
            return is_plugin_active('wp-seopress/seopress.php');
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

    public static function getTitleSeparator(): string
    {
        return Cache::remember('seo_title_separator', 60 * 60, function () {
            if (self::isRankMathActive()) {
                return get_option('rank-math-options-titles')['title_separator'] ?? '-';
            }

            return '-';
        });
    }

    public static function getMetaTitleSuffix(): string
    {
        return Cache::remember('seo_title_suffix', 60 * 60, function () {
            return sprintf('%s %s', self::getTitleSeparator(), get_bloginfo('name'));
        });
    }

    public static function getBreadcrumbs(): ?string
    {
        if (self::isRankMathActive()) {
            return rank_math_the_breadcrumbs();
        }

        return null;
    }

    public static function appendPageToMetaTitle(string $metaTitle, int|array $page): string
    {
        if (is_int($page)) {
            if ($page > 1) {
                $metaTitle = sprintf('%s %s %s %d', $metaTitle, self::getTitleSeparator(), __('Page'), $page);
            }
        } else {
            foreach ($page as $postTypeSlug => $postTypePage) {
                if (is_numeric($postTypePage) && ($postTypePrettyName = PostService::getPostPrettyNameBySlug(slug: $postTypeSlug))) {
                    $postTypePage = intval($postTypePage);

                    if ($postTypePage > 1) {
                        $metaTitle = sprintf(
                            '%s %s %s %d %s',
                            $metaTitle,
                            self::getTitleSeparator(),
                            __('Page'),
                            $postTypePage,
                            ' des ' . strtolower($postTypePrettyName)
                        );
                    }
                }
            }
        }

        return $metaTitle;
    }
}
