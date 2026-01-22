<?php

declare(strict_types=1);

namespace Adeliom\HorizonTools\Services;

use Adeliom\HorizonTools\Fields\Text\HeadingField;
use App\Blocks\Content\PostSummaryBlock;
use Extended\ACF\Key;
use Illuminate\Support\Facades\Cache;

class BlogPostService
{
    private const SUMMARY_BLOCK_NAME = 'acf/post-summary';
    private const EXCLUDED_BLOCKS = [self::SUMMARY_BLOCK_NAME];

    private static function getBlocks(bool $onlyInSummary = false): array
    {
        $pageId = !is_admin() ? get_the_ID() : $_GET['post'] ?? ($_POST['post_id'] ?? null);

        $post = get_post($pageId);

        $blocks = parse_blocks($post?->post_content);

        if (!$onlyInSummary) {
            return $blocks;
        }

        $blocksInSummary = [];

        $entryReached = false;
        $exitReached = false;

        foreach ($blocks as $block) {
            if (isset($block['blockName']) && $block['blockName'] === self::SUMMARY_BLOCK_NAME) {
                if (isset($block['attrs']['data']['top'])) {
                    if ($block['attrs']['data']['top'] == true) {
                        $entryReached = true;
                    } else {
                        $exitReached = true;
                    }
                }
            } elseif ($entryReached) {
                if (isset($block['blockName']) && $block['blockName']) {
                    $blocksInSummary[] = $block;
                }
            }

            if ($exitReached) {
                break;
            }
        }

        return $blocksInSummary;
    }

    public static function hasClosingTag(array $blocks = []): bool
    {
        $hasClosingTag = false;

        if (empty($blocks)) {
            $blocks = self::getBlocks();
        }

        $blocks = array_values(
            array_filter(
                $blocks,
                static fn($block) => is_array($block) && isset($block['blockName']) && $block['blockName'] === self::SUMMARY_BLOCK_NAME
            )
        );

        $topBlock = $blocks[0];
        $bottomBlock = $blocks[1] ?? null;

        if (empty($bottomBlock)) {
            return false;
        }

        if (!empty($bottomBlock['attrs']['data'])) {
            $bottomFields = $bottomBlock['attrs']['data'];

            if (isset($bottomFields[PostSummaryBlock::FIELD_IS_TOP])) {
                $state = $bottomFields[PostSummaryBlock::FIELD_IS_TOP];

                if ($state == false) {
                    $hasClosingTag = true;
                }
            } else {
                $fieldName = str_replace(['acf/', '-'], ['', '_'], $bottomBlock['blockName']);
                $fieldKey = sprintf('field_%s', Key::hash(sprintf('%s_%s', $fieldName, PostSummaryBlock::FIELD_IS_TOP)));

                if (isset($bottomFields[$fieldKey])) {
                    $state = $bottomFields[$fieldKey];

                    if ($state == false) {
                        $hasClosingTag = true;
                    }
                }
            }
        }

        return $hasClosingTag;
    }

    public static function getPostTitles(array $blocks = [], array $retrieveOnly = ['h2'], bool $fallbackToHtml = false): ?array
    {
        if (!empty($blocks)) {
            return self::getPostTitlesLogic(blocks: $blocks, retrieveOnly: $retrieveOnly, fallbackToHtml: $fallbackToHtml);
        } else {
            $currentId = is_admin() ? $_GET['post'] ?? ($_POST['post_id'] ?? null) : get_the_ID();

            if (null !== $currentId) {
                return Cache::remember(
                    sprintf('post-titles-%d-%s-%s', $currentId, implode('-', $retrieveOnly), $fallbackToHtml ? 'fallback-html' : ''),
                    60,
                    function () use ($retrieveOnly, $fallbackToHtml) {
                        return self::getPostTitlesLogic(retrieveOnly: $retrieveOnly, fallbackToHtml: $fallbackToHtml);
                    }
                );
            } else {
                return self::getPostTitlesLogic(retrieveOnly: $retrieveOnly, fallbackToHtml: $fallbackToHtml);
            }
        }
    }

    private static function getPostTitlesLogic(array $blocks = [], array $retrieveOnly = ['h2'], bool $fallbackToHtml = false): array
    {
        $titles = [];

        if (empty($blocks)) {
            $blocks = self::getBlocks(onlyInSummary: true);
        }

        $titleKey = sprintf('%s_%s', HeadingField::NAME, HeadingField::CONTENT_NAME);
        $titleTag = sprintf('%s_%s', HeadingField::NAME, HeadingField::TAGS_NAME);

        $excluded = array_values(
            array_merge(
                self::EXCLUDED_BLOCKS,
                array_map(function ($class) {
                    return sprintf('acf/%s', $class::$slug);
                }, ClassService::getAllCustomBlockClassesNotAllowedInSummary())
            )
        );

        foreach ($blocks as $block) {
            if (isset($block['blockName']) && !in_array($block['blockName'], $excluded)) {
                if (isset($block['attrs'], $block['attrs']['data'], $block['attrs']['data'][$titleKey])) {
                    if ($title = $block['attrs']['data'][$titleKey]) {
                        if ($retrieveOnly) {
                            if (in_array($block['attrs']['data'][$titleTag], $retrieveOnly)) {
                                $titles[] = $title;
                            }
                        } else {
                            $titles[] = $title;
                        }
                    }
                }
            }
        }

        if (empty($titles) && $fallbackToHtml) {
            $htmlTitles = self::getPostTitlesFromHtmlLogic(retrieveOnly: $retrieveOnly);

            if (!empty($htmlTitles)) {
                $titles = array_map(function ($title) {
                    return $title['content'];
                }, $htmlTitles);
            }
        }

        return $titles;
    }

    private static function getPostTitlesFromHtmlLogic(array $retrieveOnly = ['h2']): array
    {
        global $currentlyRetrievingRawTextFromPage;

        $headings = [];

        if (!$currentlyRetrievingRawTextFromPage) {
            $html = PostService::getRawTextFromPage(excludedBlocks: ['acf/post-summary'], keepTags: true);

            // Pattern pour matcher h1 à h6 avec leur contenu
            preg_match_all('/<h([1-6])[^>]*>(.*?)<\/h\1>/is', $html, $matches, PREG_SET_ORDER);

            foreach ($matches as $match) {
                $headings[] = [
                    'tag' => 'h' . $match[1],
                    'content' => trim(strip_tags($match[2])),
                ];
            }

            $headings = array_filter($headings, fn($heading) => !empty($heading['content']));
            $headings = array_filter($headings, fn($heading) => in_array($heading['tag'], $retrieveOnly));

            $headings = array_values($headings);
        }

        return $headings;
    }
}
