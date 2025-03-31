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

    public static function getReadingTimeInMinutes(int|\WP_Post $post): null|int|float
    {
        $readingTime = null;

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
}
