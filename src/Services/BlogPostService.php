<?php

declare(strict_types=1);

namespace Adeliom\HorizonTools\Services;

use Adeliom\HorizonTools\Fields\Text\HeadingField;
use App\Blocks\Content\PostSummaryBlock;
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

        if (
            isset($blocks[1], $blocks[1]['attrs'], $blocks[1]['attrs']['data'], $blocks[1]['attrs']['data'][PostSummaryBlock::FIELD_IS_TOP])
        ) {
            $state = $blocks[1]['attrs']['data'][PostSummaryBlock::FIELD_IS_TOP];

            if ($state == false) {
                $hasClosingTag = true;
            }
        }

        return $hasClosingTag;
    }

    public static function getPostTitles(array $blocks = []): ?array
    {
        if (!empty($blocks)) {
            return self::getPostTitlesLogic(blocks: $blocks);
        } else {
            $currentId = is_admin() ? $_GET['post'] ?? ($_POST['post_id'] ?? null) : get_the_ID();

            if (null !== $currentId) {
                return Cache::remember('post-titles-' . $currentId, 60, function () {
                    return self::getPostTitlesLogic();
                });
            } else {
                return self::getPostTitlesLogic();
            }
        }
    }

    private static function getPostTitlesLogic(array $blocks = []): array
    {
        $titles = [];

        if (empty($blocks)) {
            $blocks = self::getBlocks(onlyInSummary: true);
        }

        $titleKey = sprintf('%s_%s', HeadingField::NAME, HeadingField::CONTENT_NAME);
        $titleTag = sprintf('%s_%s', HeadingField::NAME, HeadingField::TAGS_NAME);

        $retrieveOnly = ['h2'];

        foreach ($blocks as $block) {
            if (isset($block['blockName']) && !in_array($block['blockName'], self::EXCLUDED_BLOCKS)) {
                if (isset($block['attrs'], $block['attrs']['data'], $block['attrs']['data'][$titleKey])) {
                    if ($title = $block['attrs']['data'][$titleKey]) {
                        if ($retrieveOnly) {
                            if(in_array($block['attrs']['data'][$titleTag], $retrieveOnly)){
                                $titles[] = $title;
                            }
                        } else {
                            $titles[] = $title;
                        }
                    }
                }
            }
        }

        return $titles;
    }
}