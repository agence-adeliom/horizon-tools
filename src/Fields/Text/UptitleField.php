<?php

declare(strict_types=1);

namespace Adeliom\HorizonTools\Fields\Text;

use Extended\ACF\Fields\Text;

class UptitleField
{
	final public const NAME = 'uptitle';
	final public const LABEL = 'Sur-titre';

	public static function make(string $label = self::LABEL, ?string $name = self::NAME): Text
	{
		return Text::make($label, $name);
	}
}