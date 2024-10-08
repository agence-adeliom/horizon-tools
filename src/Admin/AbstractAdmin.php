<?php

declare(strict_types=1);

namespace Adeliom\HorizonTools\Admin;

use Extended\ACF\Location;
use Roots\Acorn\Exceptions\SkipProviderException;

abstract class AbstractAdmin
{
    public static ?string $title = null;
    public static ?string $slug = null;
    public static bool $isOptionPage = false;
    public static ?string $optionPageIcon = null;

    public function __construct()
    {
        if (null === $this::$title) {
            throw new SkipProviderException(static::class . ' : You must define a title for your admin');
        }
    }

    public function getSlug(): ?string
    {
        return $this::$slug ?? sanitize_title($this::$title);
    }

    public function getOptionPageParams(): array
    {
        return [
            'page_title' => $this::$title,
            'menu_title' => '',
            'autoload' => 0,
            'position' => '',
            'redirect' => 0,
            'description' => '',
            'icon_url' => '',
            'update_button' => 'Mise à jour',
            'updated_message' => 'Options mises à jour',
            'advanced_configuration' => 0,
            'menu_slug' => $this->getSlug(),
            'capability' => 'edit_theme_options',
            'menu_icon' => [
                'type' => 'dashicons',
                'value' => 'dashicons-admin-generic',
            ],
        ];
    }

    public function getOptionPageParent(): ?string
    {
        return null;
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

    public function getLocation(): iterable
    {
        if (function_exists('acf_add_options_page') && static::$isOptionPage) {
            yield Location::where('options_page', static::getSlug());
        }

        return [];
    }
}
