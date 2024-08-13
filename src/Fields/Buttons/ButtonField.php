<?php

declare(strict_types=1);

namespace Adeliom\HorizonTools\Fields\Buttons;

use Extended\ACF\Fields\Group;
use Extended\ACF\Fields\Link;
use Extended\ACF\Fields\Select;
use Extended\ACF\Fields\Text;

class ButtonField
{

	public const BUTTON = "button";
	public const BUTTON_TYPE = "type";
	public const BUTTON_LINK = "link";

	public const BUTTONS = "buttons";
	public const BUTTON_ONE = "one";
	public const BUTTON_TWO = "two";

	final public const NAME = 'uptitle';
	final public const LABEL = 'Sur-titre';

	public static function make(string $label = "Bouton", string|null $name = self::BUTTON): Group
	{
		return Group::make(__('Bouton'), $name)
			->fields([
				Link::make($label, self::BUTTON_LINK)
			]);
	}

	public static function types(string $title = "Bouton", string|null $typeInstructions = "", string|null $name = self::BUTTON): Group
	{
		return Group::make($title, $name)
			->fields([
				Select::make("Type", self::BUTTON_TYPE)
					->choices([
						"primary" => __("Primaire"),
						"secondary" => __("Secondaire"),
						"tertiary" => __("Tertiaire"),
					])
					->default("primary")
					->stylized()
					->helperText($typeInstructions),
				Link::make("Lien", self::BUTTON_LINK)
			]);
	}

	/**
	 * Groupe de deux boutons
	 */
	public static function group(bool $withType = false): Group
	{
		$fields = [
			self::make(__("Bouton principal"), self::BUTTON_ONE),
			self::make(__("Bouton secondaire"), self::BUTTON_TWO),
		];

		if ($withType) {
			$fields = [
				self::types(__("Bouton principal"), "", self::BUTTON_ONE),
				self::types(__("Bouton secondaire"), "", self::BUTTON_TWO),
			];
		}

		return Group::make(__("Boutons"), self::BUTTONS)->fields($fields);
	}
}
