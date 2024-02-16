<?php

declare(strict_types=1);

namespace LucasVigneron\SageTools\PostTypes;

use Extended\ACF\Location;

abstract class AbstractPostType
{
	abstract public static function getSlug(): string;

	public function getConfig(array $config = []): array
	{
		return array_merge_recursive([
			'post_type' => $this->getSlug(),
			'args'=> [
				'public'=>true,
				'show_in_menu'=>true,
				'show_in_rest'=>true,
				'show_in_nav_menus'=>true,
			]
		], $config);
	}

	public function getFieldsTitle(): string
	{
		return sprintf('Champs additionnels (%s)', $this->getSlug());
	}

	public function getFieldsPosition(): string
	{
		return 'side';
	}

	public function getFields(): ?iterable
	{
		return null;
	}

	public function getFieldsLocation(): iterable
	{
		yield Location::where('post_type', $this->getSlug());
	}
}
