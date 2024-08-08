<?php

declare(strict_types=1);

namespace Adeliom\HorizonTools\Services;

use Extended\ACF\Fields\Group;
use Extended\ACF\Fields\Repeater;

class AcfService
{
	public static function getChoices(iterable $fields, string $fullMetaKey, ?string &$buildKey = '', bool $first = true)
	{
		foreach ($fields as $field) {
			if ($first) {
				$buildKey = '';
			}
			switch (true) {
				case $field instanceof Group:
				case $field instanceof Repeater:
				foreach ((array)$field as $key => $value) {
					if (str_contains($key, 'settings')) {

						if (isset($value['sub_fields'])) {
							if (!empty($buildKey)) {
								$buildKey .= '_';
							}

							$buildKey .= $value['name'];

							if ($results = self::getChoices($value['sub_fields'], $fullMetaKey, $buildKey, false)) {
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
					if (isset($value['name'])) {
						$shouldBe = empty($buildKey) ? $value['name'] : $buildKey . '_' . $value['name'];

						if ($shouldBe === $fullMetaKey) {
							if (isset($value['choices'])) {
								return $value['choices'];
							}
						}

					}
				}
			}
		}

		return false;
	}
}