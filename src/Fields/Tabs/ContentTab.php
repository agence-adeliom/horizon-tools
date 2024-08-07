<?php

declare(strict_types=1);

namespace Adeliom\HorizonTools\Fields\Tabs;

use Adeliom\HorizonTools\Fields\Tabs\Traits\Fields;
use Extended\ACF\Fields\Tab;

class ContentTab extends Tab
{
	use Fields;

	final public const TAB_KEY = 'content_tab';

	public static function make(string $label = 'Contenu', ?string $name = self::TAB_KEY): static
	{
		return parent::make($label, $name);
	}
}