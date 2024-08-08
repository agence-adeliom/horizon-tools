<?php

declare(strict_types=1);

namespace Adeliom\HorizonTools\Fields\Text;

class IconField
{
	final public const LABEL = 'Icône';
	final public const NAME = 'icon';

	public static function make(string $label = self::LABEL, ?string $name = self::NAME): FontAwesomeIcon
	{
		return FontAwesomeIcon::make($label, $name);
	}
}