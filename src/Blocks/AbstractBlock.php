<?php

declare(strict_types=1);

namespace LucasVigneron\SageTools\Blocks;

use Roots\Acorn\Exceptions\SkipProviderException;

abstract class AbstractBlock
{
	public static ?string $title = null;

	public static ?string $slug = null;

	public static string $category = 'common';

	public static ?string $icon = null;

	public function __construct()
	{
		if (null === $this::$slug) {
			throw new SkipProviderException(static::class . ' : You must define a slug for your block');
		}

		if (null === $this::$title) {
			throw new SkipProviderException(static::class . ' : You must define a title for your block');
		}
	}

	final public static function getFullBlockName(): string
	{
		return 'acf/' . self::$slug;
	}

	public function getBlockFields(): ?iterable
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
}
