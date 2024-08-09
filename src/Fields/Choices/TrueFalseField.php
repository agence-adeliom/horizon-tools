<?php

declare(strict_types=1);

namespace Adeliom\HorizonTools\Fields\Choices;

use Extended\ACF\Fields\TrueFalse;

class TrueFalseField
{
	final public const LABEL = 'Vrai/Faux';
	final public const NAME = 'true_false';

	public static function make(?string $label = self::LABEL, ?string $name = self::NAME): TrueFalse
	{
		return TrueFalse::make($label, $name)
			->stylized();
	}
}