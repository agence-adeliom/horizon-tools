<?php

declare(strict_types=1);

namespace Adeliom\HorizonTools\Fields\Tabs;

use Adeliom\HorizonTools\Fields\Tabs\Traits\Fields;
use Extended\ACF\Fields\Tab;

class LayoutTab extends Tab
{
	use Fields;

	final public const TAB_KEY = 'layout_tab';

	public static function make(string $label = 'Mise en page', ?string $name = self::TAB_KEY): static
	{
		return parent::make($label, $name);
	}
}