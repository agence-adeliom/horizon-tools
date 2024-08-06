<?php

declare(strict_types=1);

namespace Adeliom\HorizonTools\Taxonomies;

use Roots\Acorn\Exceptions\SkipProviderException;

abstract class AbstractTaxonomy
{
    public static ?string $slug = null;

    public function __construct()
    {
        if (null === $this::$slug) {
            throw new SkipProviderException(static::class . ' : You must define a slug for your taxonomy');
        }
    }

    abstract public function getPostTypes(): array;

    public function getConfig(array $config = []): array
    {
        return array_replace_recursive([
            'taxonomy' => $this::$slug,
            'object_type' => $this->getPostTypes(),
            'args' => [
                'public' => true,
                'show_in_menu' => true,
                'show_in_rest' => true,
                'show_in_nav_menus' => true,
            ]
        ], $config);
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
        return "default";
    }

    public function getPosition(): string
    {
        return "acf_after_title";
    }

    public function getLabelPlacement(): string
    {
        return "top";
    }

    public function getInstructionPlacement(): string
    {
        return "label";
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
            'featured_image'
        ];
    }

    public function getMenuOrder(): int
    {
        return 0;
    }
}
