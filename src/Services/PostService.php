<?php

declare(strict_types=1);

namespace Adeliom\HorizonTools\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use WP_Query;

class PostService
{
    private const WORDS_PER_MINUTE = 200;
    private const DEFAULT_POST_TYPES = ['post', 'page'];

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

    public static function getAllPostTypeSlugs(): array
    {
        $slugs = ['post', 'page'];

        foreach (ClassService::getAllCustomPostTypeClasses() as $postTypeClass) {
            if (class_exists($postTypeClass)) {
                $postType = new $postTypeClass();

                if (property_exists($postType, 'slug')) {
                    $slugs[] = $postType::$slug;
                }
            }
        }

        return $slugs;
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

    public static function getPostPrettyNameBySlug(string $slug, bool $plural = true): ?string
    {
        $prettyName = null;

        switch ($slug) {
            case 'page':
                $prettyName = $plural ? __('Pages') : __('Page');
                break;
            case 'post':
                $prettyName = $plural ? __('Articles') : __('Article');
                break;
            default:
                if ($postTypeClass = ClassService::getPostTypeClassBySlug($slug)) {
                    $postTypeInstance = new $postTypeClass();

                    if (method_exists($postTypeInstance, 'getConfig')) {
                        if ($postTypeConfig = $postTypeInstance->getConfig()) {
                            if (!empty($postTypeConfig['args']['labels']['name'])) {
                                $prettyName = $postTypeConfig['args']['labels']['name'];
                            }

                            if (!$plural && !empty($postTypeConfig['args']['labels']['singular_name'])) {
                                $prettyName = $postTypeConfig['args']['labels']['singular_name'];
                            }
                        }
                    }
                }
                break;
        }

        return $prettyName;
    }

    public static function getCardByPostType(string $postType): ?string
    {
        return Cache::remember('card_for_' . $postType, 60 * 60, function () use ($postType) {
            $card = null;

            if (in_array($postType, self::DEFAULT_POST_TYPES)) {
                $card = Config::get(sprintf('posts.listing.cards.%s', $postType));

                if (empty($card)) {
                    throw new \Exception(
                        sprintf(
                            'You have to set a card for the post-type "%s" in the "posts.php" config file (posts.listing.cards.%s)',
                            $postType,
                            $postType
                        )
                    );
                }
            } elseif (null !== $postType) {
                $postTypeClass = ClassService::getPostTypeClassBySlug($postType);
                $card = $postTypeClass::$card;

                if (empty($card)) {
                    throw new \Exception(
                        sprintf(
                            'You have to set a card for the post-type in the class "%s". It should be a static var $card',
                            $postTypeClass
                        )
                    );
                }
            }

            return $card;
        });
    }

    /**
     * Renders a post in a simulated single post context.
     *
     * This method sets up the WordPress global context as if viewing a single post,
     * renders the 'single' view, and then restores the original context.
     *
     * @param \WP_Post|int $post The post object or post ID to render.
     * @return string The rendered HTML output of the single post view.
     *
     * @throws \Exception If the post cannot be found or if WordPress context manipulation fails.
     */
    public static function renderPost(\WP_Post|int $post)
    {
        if (is_int($post)) {
            $post = get_post($post);
        }

        // Save the complete state
        global $wp_query, $wp_the_query, $wp, $wp_admin_bar;
        $original_query = $wp_query;
        $original_the_query = $wp_the_query;
        $original_wp = $wp;
        $original_post = $GLOBALS['post'] ?? null;
        $original_admin_bar = $wp_admin_bar;

        // Create the query
        $query = new WP_Query([
            'p' => $post->ID,
            'post_type' => 'any',
            'posts_per_page' => 1,
        ]);

        // Update global variables
        $wp_query = $query;
        $wp_the_query = $query;

        // Simulate single context
        $query->is_single = true;
        $query->is_singular = true;
        $query->is_home = false;
        $query->is_front_page = false;
        $query->is_404 = false;
        $query->is_admin = false;

        // Force environment variables
        $GLOBALS['pagenow'] = 'index.php';

        // Initialize WP if necessary
        if (!$wp) {
            $wp = new \WP();
        }

        // CRUCIAL : Initialise proprement l'admin bar
        if (is_user_logged_in() && !is_admin()) {
            add_filter('show_admin_bar', '__return_true');

            // Initialise l'admin bar AVANT les hooks
            if (!$wp_admin_bar) {
                _wp_admin_bar_init();
            }
        }

        // Render the view
        $view = view('single')->render();

        // Restore everything
        $wp_query = $original_query;
        $wp_the_query = $original_the_query;
        $wp = $original_wp;
        $wp_admin_bar = $original_admin_bar;
        $GLOBALS['post'] = $original_post;
        wp_reset_postdata();

        return $view;
    }
}
