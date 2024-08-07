<?php

declare(strict_types=1);

namespace Adeliom\HorizonTools\Fields\Tabs;

use Adeliom\HorizonTools\Fields\Tabs\Traits\Fields;
use Extended\ACF\Fields\Tab;

class GlobalTab extends Tab
{
	use Fields;

	final public const TAB_KEY = 'global_tab';

	public static function make(string $label = 'Global', ?string $name = null): static
	{
		return parent::make($label, $name);
	}
}