<?php

declare(strict_types=1);

namespace Adeliom\HorizonTools\Blocks;

use Roots\Acorn\Exceptions\SkipProviderException;

abstract class AbstractBlock
{
    public static ?string $title = null;

    public static ?string $description = null;

    public static ?string $slug = null;

    public static string $category = 'common';

    public static ?string $mode = 'edit';

    public static ?string $icon = null;
    public static bool $inSummary = true;

    public function __construct()
    {
        if (null === $this::$slug) {
            throw new SkipProviderException(static::class . ' : You must define a slug for your block');
        }

        if (null === $this::$title) {
            throw new SkipProviderException(static::class . ' : You must define a title for your block');
        }
    }

    final public static function getFullName(): string
    {
        return 'acf/' . static::$slug;
    }

    public function getFields(): ?iterable
    {
        return null;
    }

    public function getPostTypes(): ?array
    {
        return null;
    }

    public function renderBlockCallback(): void
    {
        // do nothing
    }

    public function addToContext(): array
    {
        return [];
    }

    public function getSupports(): array
    {
        return [
            'align' => false,
            'anchor' => true,
            'className' => true,
            'customClassName' => true,
            'jsx' => false,
            'renaming' => false,
        ];
    }

    public function getExample(): array
    {
        return [
            'attributes' => [
                'mode' => 'preview',
                'data' => ['_is_preview' => true],
            ],
        ];
    }
}
