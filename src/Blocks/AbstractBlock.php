<?php

declare(strict_types=1);

namespace LucasVigneron\SageTools\Blocks;

abstract class AbstractBlock
{
	abstract public function getBlockTitle(): string;

	abstract public static function getBlockName(): string;

	final public static function getFullBlockName(): string
	{
		return 'acf/' . static::getBlockName();
	}

	public function getBlockFields(): ?iterable
	{
		return null;
	}

	public function getBlockCategory(): string
	{
		return 'common';
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
