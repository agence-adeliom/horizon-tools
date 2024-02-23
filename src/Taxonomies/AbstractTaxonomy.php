<?php

declare(strict_types=1);

namespace LucasVigneron\SageTools\Taxonomies;

use Extended\ACF\Location;
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
		return array_merge_recursive([
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
		return sprintf('Champs additionnels (%s)', $this::$slug);
	}

	public function getFields(): ?iterable
	{
		return null;
	}

	public function getFieldsLocation(): iterable
	{
		yield Location::where('taxonomy', $this::$slug);
	}
}
