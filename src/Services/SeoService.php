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

    public static function isYoastSEOActive(): bool
    {
        if (function_exists('is_plugin_active')) {
            return is_plugin_active('wordpress-seo/wp-seo.php');
        }

        return false;
    }

    /**
     * Checks if the current page or a given post is indexed by the active SEO plugin (Yoast, Rank Math, or SEOPress).
     *
     * @param null|int|\WP_Post $post The post to check, or null for the current post.
     * @return bool True if indexed, false otherwise.
     */
    public static function isCurrentPageIndexed(null|int|\WP_Post $post = null): bool
    {
        $isIndexed = true;

        if ($post instanceof \WP_Post) {
            $post = $post->ID;
        }

        if (null === $post) {
            $post = get_the_ID();
        }

        if (is_int($post)) {
            switch (true) {
                case self::isYoastSEOActive():
                    $isIndexed = self::isCurrentPageIndexedByYoast(post: $post);
                    break;
                case self::isRankMathActive():
                    $isIndexed = self::isCurrentPageIndexedByRankMath(post: $post);
                    break;
                case self::isSEOPressActive():
                    $isIndexed = self::isCurrentPageIndexedBySEOPress(post: $post);
                    break;
                default:
                    break;
            }
        }

        return $isIndexed;
    }

    private static function isCurrentPageIndexedByYoast(null|int|\WP_Post $post): bool
    {
        if (!self::isYoastSEOActive()) {
            return false;
        }

        if ($post instanceof \WP_Post) {
            $post = $post->ID;
        }

        if (null === $post) {
            $post = get_the_ID();
        }

        $noindex = get_post_meta($post, '_yoast_wpseo_meta-robots-noindex', true);

        return $noindex !== '1';
    }

    private static function isCurrentPageIndexedByRankMath(null|int|\WP_Post $post = null): bool
    {
        if (!self::isRankMathActive()) {
            return false;
        }

        if ($post instanceof \WP_Post) {
            $post = $post->ID;
        }

        if (null === $post) {
            $post = get_the_ID();
        }

        // RankMath stocke l'indexation dans le champ meta 'rank_math_robots'
        $robots = get_post_meta($post, 'rank_math_robots', true);

        // Si le champ existe et contient 'noindex', la page n'est pas indexée
        if (is_array($robots)) {
            return !in_array('noindex', $robots);
        } elseif (is_string($robots)) {
            return stripos($robots, 'noindex') === false;
        }

        // Si le champ n'existe pas, on considère la page indexée
        return true;
    }

    private static function isCurrentPageIndexedBySEOPress(null|int|\WP_Post $post = null): bool
    {
        if (!self::isSEOPressActive()) {
            return false;
        }

        if ($post instanceof \WP_Post) {
            $post = $post->ID;
        }

        if (null === $post) {
            $post = get_the_ID();
        }

        // SEOPress stocke l'indexation dans le champ meta '_seopress_robots_index'
        $noindex = get_post_meta($post, '_seopress_robots_index', true);

        // Si la valeur est 'noindex', la page n'est pas indexée
        return $noindex !== 'yes';
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
