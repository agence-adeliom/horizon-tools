<?php

declare(strict_types=1);

namespace Adeliom\SageTools\Services;

use Illuminate\Support\Facades\Validator;
use Livewire\Component;

class ValidationService
{
	public const REGEX_PHONE = '/^(\+33[-\s]?|0)[1-9]([-\s]?\d{2}){4}$/';
	public const REGEX_EMAIL = '/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/';
	public const REGEX_EMAIL_OR_EMPTY = '/^([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})?$/';

	public static function areAllLivewireModelsValid(Component $component): bool
	{
		$fields = [];

		foreach (array_keys($component->getRules()) as $field) {
			if (property_exists($component, $field)) {
				$fields[$field] = $component->$field;
			}
		}

		$validator = Validator::make($fields, $component->getRules());

		return $validator->fails();
	}
}
