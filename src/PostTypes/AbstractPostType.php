<?php

declare(strict_types=1);

namespace Adeliom\HorizonTools\PostTypes;

use Adeliom\HorizonTools\Database\QueryBuilder;
use Roots\Acorn\Exceptions\SkipProviderException;

abstract class AbstractPostType
{
    public static ?string $slug = null;
    protected ?QueryBuilder $queryBuilder = null;

    public const CUSTOM_COLUMN_LABEL = 'label';
    public const CUSTOM_COLUMN_KEY = 'key';
    public const CUSTOM_COLUMN_DISPLAY = 'display';
    public const CUSTOM_COLUMN_CALLBACK = 'callback';
    public const CUSTOM_COLUMN_SORTABLE = 'sortable';
    public const CUSTOM_COLUMN_TAXONOMY = 'taxonomy';

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

    public function getCustomColumns(): ?array
    {
        return null;
    }

    public function getSearchResultsTitle(): string
    {
        return __('Tous les résultats :') . ' ' . ($this->getConfig()['args']['labels']['name'] ?? static::$slug);
    }

    /**
     * Returns an array of field keys that should be searched in the post-type.
     * @return string[]|null
     */
    public static function getSearchableFields(): ?array
    {
        return null;
    }

    public function createQueryBuilder(): false|QueryBuilder
    {
        if (empty(static::$slug)) {
            return false;
        }

        return (new QueryBuilder())->postType(static::$slug);
    }
}
