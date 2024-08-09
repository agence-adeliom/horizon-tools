<?php

declare(strict_types=1);

namespace Adeliom\HorizonTools\Fields\Medias;

use Adeliom\HorizonTools\Fields\Choices\TrueFalseField;
use Extended\ACF\ConditionalLogic;
use Extended\ACF\Fields\File;
use Extended\ACF\Fields\Group;
use Extended\ACF\Fields\Text;

class VideoField
{
	final public const VIDEO = "video";
	final public const THUMBNAIL = "thumbnail";
	final public const VIDEO_FILE = "file";
	final public const IS_YOUTUBE = "is_youtube";
	final public const ID_YOUTUBE = "id";

	/**
	 * Vidéo
	 */
	public static function make(): Group
	{
		return Group::make("Vidéo", self::VIDEO)
			->fields([

				ImageField::make("Vignette", self::THUMBNAIL)
					->required(),

				TrueFalseField::make("Vidéo YouTube ?", self::IS_YOUTUBE),

				Text::make('Identifiant de la vidéo', self::ID_YOUTUBE)
					->conditionalLogic([
						ConditionalLogic::where(self::IS_YOUTUBE, "==", 1)
					]),

				File::make("Fichier", self::VIDEO_FILE)
					->acceptedFileTypes(["mp4"])
					->format("array")
					->required()
					->conditionalLogic([
						ConditionalLogic::where(self::IS_YOUTUBE, "!=", 1),
					])

			]);
	}
}