<?php

declare(strict_types=1);

namespace Adeliom\HorizonTools\Fields\Medias;

use Extended\ACF\ConditionalLogic;
use Extended\ACF\Fields\Group;
use Extended\ACF\Fields\Select;

class MediaField
{
	final public const HAS_IMAGE = "image";
	final public const HAS_VIDEO = "video";

	final public const MEDIA = "media";
	final public const TYPE = "type";

	public static function make(string $instructions = "", array $includes = [
		self::HAS_IMAGE,
		self::HAS_VIDEO,
	]): Group
	{

		$choices = [];

		$hasImage = in_array(self::HAS_IMAGE, $includes, true);
		$hasVideo = in_array(self::HAS_VIDEO, $includes, true);

		if ($hasImage) {
			$choices[self::HAS_IMAGE] = "Image";
		}

		if ($hasVideo) {
			$choices[self::HAS_VIDEO] = "Vidéo";
		}

		$fields = [
			Select::make("Type", self::TYPE)
				->choices($choices)
				->helperText("Choisir le type de média")
				->stylized()
				->required()
		];

		if ($hasImage) {
			$imageField = ImageField::make()
				->conditionalLogic([
					ConditionalLogic::where(self::TYPE, "==", self::HAS_IMAGE),
				]);

			$fields[] = $imageField;
		}

		if ($hasVideo) {
			$videoField = VideoField::make()->conditionalLogic([
				ConditionalLogic::where(self::TYPE, "==", self::HAS_VIDEO),
			]);

			$fields[] = $videoField;
		}

		return Group::make("Média", self::MEDIA)
			->helperText($instructions)
			->fields($fields);
	}

	public static function image(string $instructions = ""): Group
	{
		return Group::make("Média", self::MEDIA)
			->helperText($instructions)
			->fields([
				ImageField::make()->conditionalLogic([
					ConditionalLogic::where(self::TYPE, "==", self::HAS_IMAGE),
				])
			]);
	}

	public static function video(string $instructions = ""): Group
	{
		return Group::make("Média", self::MEDIA)
			->helperText($instructions)
			->fields([
				VideoField::make()->conditionalLogic([
					ConditionalLogic::where(self::TYPE, "==", self::HAS_VIDEO),
				])
			]);
	}
}