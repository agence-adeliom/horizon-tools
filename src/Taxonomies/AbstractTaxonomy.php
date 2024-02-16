<?php

declare(strict_types=1);

namespace LucasVigneron\SageTools\Taxonomies;

use Extended\ACF\Location;

abstract class AbstractTaxonomy
{
	abstract public static function getSlug(): string;

	abstract public function getPostTypes(): array;

	public function getConfig(array $config = []): array
	{
		return array_merge_recursive([
			'taxonomy' => $this->getSlug(),
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
		return sprintf('Champs additionnels (%s)', $this->getSlug());
	}

	public function getFields(): ?iterable
	{
		return null;
	}

	public function getFieldsLocation(): iterable
	{
		yield Location::where('taxonomy', $this->getSlug());
	}
}
