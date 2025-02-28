<?php

declare(strict_types=1);

namespace Adeliom\HorizonTools\PostTypes;

use Extended\ACF\Location;
use Roots\Acorn\Exceptions\SkipProviderException;

abstract class AbstractPostType
{
    public static ?string $slug = null;
    public static bool $searchable = false;

    public function __construct()
    {
        if (null === $this::$slug) {
            throw new SkipProviderException(static::class . ' : You must define a slug for your post type');
        }
    }

    public function getConfig(array $config = []): array
    {
        return array_replace_recursive(
            [
                'post_type' => $this::$slug,
                'args' => [
                    'public' => true,
                    'show_in_menu' => true,
                    'show_in_rest' => true,
                    'show_in_nav_menus' => true,
                ],
            ],
            $config
        );
    }

    public function getFieldsTitle(): string
    {
        return __('Champs additionnels');
    }

    public function getFields(): ?iterable
    {
        return null;
    }

    public function getStyle(): string
    {
        return 'default';
    }

    public function getPosition(): string
    {
        return 'acf_after_title';
    }

    public function getLabelPlacement(): string
    {
        return 'top';
    }

    public function getInstructionPlacement(): string
    {
        return 'label';
    }

    public function getHideOnScreen(): array
    {
        return [
            'the_content',
            'excerpt',
            'discussion',
            'comments',
            'slug',
            'author',
            'format',
            'categories',
            'tags',
            'send-trackbacks',
            'featured_image',
        ];
    }

    public function getMenuOrder(): int
    {
        return 0;
    }
}
