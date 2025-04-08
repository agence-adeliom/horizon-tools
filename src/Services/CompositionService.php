<?php

declare(strict_types=1);

namespace Adeliom\HorizonTools\Services;

use Adeliom\HorizonTools\Database\QueryBuilder;

class CompositionService
{
    /**
     * @return \WP_Post[]
     */
    public static function getAllCompositions(): array
    {
        return (new QueryBuilder())->postType('wp_block')->get();
    }

    public static function getCompositionChoices(): array
    {
        $choices = [];

        foreach (self::getAllCompositions() as $composition) {
            $choices[$composition->ID] = $composition->post_title;
        }

        return $choices;
    }

    public static function renderComposition(int $id): ?string
    {
        $renderedBlock = null;
        $block = (new QueryBuilder())->postType(postType: 'wp_block')->whereIdIn(ids: $id)->getOneOrNull();

        if ($block) {
            $parsedBlock = parse_blocks($block->post_content);

            $blockData = $parsedBlock[0] ?? null;

            if ($blockData) {
                $renderedBlock = render_block($blockData);
            }
        }

        return $renderedBlock;
    }
}
