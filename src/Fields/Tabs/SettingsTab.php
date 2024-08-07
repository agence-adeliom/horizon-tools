<?php

declare(strict_types=1);

namespace Adeliom\HorizonTools\Fields\Tabs;

use Adeliom\HorizonTools\Fields\Tabs\Traits\Fields;
use Extended\ACF\Fields\Tab;

class SettingsTab extends Tab
{
	use Fields;

	final public const TAB_KEY = 'settings';

	public static function make(string $label = 'Paramètres', ?string $name = null): static
	{
		return parent::make($label, $name);
	}
}