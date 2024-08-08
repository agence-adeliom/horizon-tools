<?php

declare(strict_types=1);

namespace Adeliom\HorizonTools\Services;

use Extended\ACF\Fields\Group;
use Extended\ACF\Fields\Repeater;

class AcfService
{
	public static function getChoices(iterable $fields, string $metaKey)
	{
		foreach ($fields as $field) {
			switch (true) {
				case $field instanceof Group:
				case $field instanceof Repeater:
					foreach ((array)$field as $key => $value) {
						if (str_contains($key, 'settings')) {

							if (isset($value['sub_fields'])) {
								if ($results = self::getChoices($value['sub_fields'], $metaKey)) {
									return $results;
								}
							}
						}
					}
					break;
				default:
					break;
			}

			foreach ((array)$field as $key => $value) {
				if (str_contains($key, 'settings')) {
					if (isset($value['name']) && $value['name'] === $metaKey) {
						if (isset($value['choices'])) {
							return $value['choices'];
						}
					}
				}
			}
		}

		return false;
	}
}