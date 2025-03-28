<?php

declare(strict_types=1);

namespace Adeliom\HorizonTools\Services;

use Adeliom\HorizonTools\Fields\Text\HeadingField;
use App\Blocks\Content\PostSummaryBlock;

class BlogPostService
{
    private const SUMMARY_BLOCK_NAME = 'acf/post-summary';
    private const EXCLUDED_BLOCKS = [self::SUMMARY_BLOCK_NAME];

    private static function getBlocks(): array
    {
        $pageId = !is_admin() ? get_the_ID() : $_GET['post'] ?? ($_POST['post_id'] ?? null);

        return parse_blocks(get_the_content());
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

    public static function getPostTitles(array $blocks = []): array
    {
        $titles = [];

        if (empty($blocks)) {
            $blocks = self::getBlocks();
        }

        $titleKey = sprintf('%s_%s', HeadingField::NAME, HeadingField::CONTENT_NAME);

        foreach ($blocks as $block) {
            if (isset($block['blockName']) && !in_array($block['blockName'], self::EXCLUDED_BLOCKS)) {
                if (isset($block['attrs'], $block['attrs']['data'], $block['attrs']['data'][$titleKey])) {
                    if ($title = $block['attrs']['data'][$titleKey]) {
                        $titles[] = $title;
                    }
                }
            }
        }

        return $titles;
    }
}
