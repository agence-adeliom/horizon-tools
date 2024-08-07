<?php

declare(strict_types=1);

namespace Adeliom\HorizonTools\Fields\Select;

use Adeliom\HorizonTools\Services\ClassService;
use Extended\ACF\Fields\Select;

class PostTypeSelectField
{
	final public const LABEL = 'Type de contenu';
	final public const NAME = 'postType';

	/**
	 * @param string $label
	 * @param string|null $name
	 * @param array|null $excluded Array of post type slugs to exclude
	 * @return Select
	 */
	public static function make(string $label = self::LABEL, ?string $name = self::NAME, ?array $excluded = null): Select
	{
		$choices = [
			'post' => 'Articles',
			'page' => 'Pages',
		];

		if (null !== $excluded) {
			$choices = array_diff_key($choices, array_flip($excluded));
		}

		return Select::make(__($label), $name)
			->choices($choices)
			->stylized();
	}
}