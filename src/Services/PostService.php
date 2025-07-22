<?php

declare(strict_types=1);

namespace Adeliom\HorizonTools\Services;

class PostService
{
    private const WORDS_PER_MINUTE = 200;

    private static function handleBlock(array $block, int &$wordCount): void
    {
        if (!empty($block['blockName'])) {
            if (!empty($block['attrs']['data'])) {
                foreach ($block['attrs']['data'] as $key => $data) {
                    self::handleField(key: $key, field: $data, wordCount: $wordCount);
                }
            }
        }
    }

    private static function handleField(string $key, mixed $field, int &$wordCount): void
    {
        if (str_starts_with($key, '_') || is_numeric($field) || empty($field)) {
            return;
        }

        switch (true) {
            case is_string($field):
                $wordCount += str_word_count(strip_tags($field));
                break;
            default:
                break;
        }
    }

    public static function getReadingTimeInMinutes(null|int|\WP_Post $post = null): null|int|float
    {
        $readingTime = null;

        if (null === $post) {
            $post = get_the_ID();
        }

        if (!$post) {
            return null;
        }

        if (is_int($post)) {
            $post = get_post($post);
        }

        if ($post instanceof \WP_Post) {
            $content = $post->post_content;
            $blocks = parse_blocks($content);

            $wordCount = 0;

            foreach ($blocks as $block) {
                self::handleBlock(block: $block, wordCount: $wordCount);
            }

            $readingTime = $wordCount / self::WORDS_PER_MINUTE;
        }

        return $readingTime;
    }

    public static function getAllAssociatedTaxonomies(string $postType, array $excluded = [], bool $onlySlugs = false): array
    {
        $taxonomySlugs = [];

        if ($postType === 'post') {
            if (!in_array('post_tag', $excluded)) {
                $excluded[] = 'post_tag';
            }

            if (!in_array('post_format', $excluded)) {
                $excluded[] = 'post_format';
            }
        }

        $taxonomySlugs = get_object_taxonomies($postType);
        $taxonomySlugs = array_values(array_diff($taxonomySlugs, $excluded));

        if ($onlySlugs) {
            return $taxonomySlugs;
        }

        $taxonomyAssociation = [];

        foreach ($taxonomySlugs as $taxonomySlug) {
            if ($taxonomy = get_taxonomy($taxonomySlug)) {
                if ($taxonomy instanceof \WP_Taxonomy) {
                    $taxonomyAssociation[$taxonomySlug] = $taxonomy->label;
                }
            }
        }

        return $taxonomyAssociation;
    }

    public static function getPostPrettyNameBySlug(string $slug): ?string
    {
        $prettyName = null;

        switch ($slug) {
            case 'page':
                $prettyName = __('Page');
                break;
            case 'post':
                $prettyName = __('Article');
                break;
            default:
                if ($postTypeClass = ClassService::getPostTypeClassBySlug($slug)) {
                    $postTypeInstance = new $postTypeClass();

                    if (method_exists($postTypeInstance, 'getConfig')) {
                        if ($postTypeConfig = $postTypeInstance->getConfig()) {
                            if (!empty($postTypeConfig['args']['labels']['name'])) {
                                $prettyName = $postTypeConfig['args']['labels']['name'];
                            }
                        }
                    }
                }
                break;
        }

        return $prettyName;
    }
}
