<?php

declare(strict_types=1);

namespace Adeliom\HorizonTools\Fields\Tabs\Traits;

use Extended\ACF\Fields\Tab;

trait Fields
{
	public function fields(array $fields): \Generator
	{
		yield Tab::make($this->settings["label"], $this->settings["name"]);
		foreach ($fields as $field) {
			yield $field;
		}
	}
}