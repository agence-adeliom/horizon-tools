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
		return Group::make(__("Vidéo", 'horizon-tools'), self::VIDEO)
			->fields([

				ImageField::make(__("Vignette", 'horizon-tools'), self::THUMBNAIL)
					->required(),

				TrueFalseField::make(__("Vidéo YouTube ?", 'horizon-tools'), self::IS_YOUTUBE),

				Text::make(__('Identifiant de la vidéo', 'horizon-tools'), self::ID_YOUTUBE)
					->conditionalLogic([
						ConditionalLogic::where(self::IS_YOUTUBE, "==", 1)
					]),

				File::make(__("Fichier", 'horizon-tools'), self::VIDEO_FILE)
					->acceptedFileTypes(["mp4"])
					->format("array")
					->required()
					->conditionalLogic([
						ConditionalLogic::where(self::IS_YOUTUBE, "!=", 1),
					])

			]);
	}
}