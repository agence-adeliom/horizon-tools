<?php

declare(strict_types=1);

namespace Adeliom\HorizonTools\Fields\Medias;

use Adeliom\HorizonTools\Fields\Choices\TrueFalseField;
use Adeliom\HorizonTools\Fields\Text\WysiwygField;
use Extended\ACF\ConditionalLogic;
use Extended\ACF\Fields\File;
use Extended\ACF\Fields\Group;
use Extended\ACF\Fields\Text;
use Illuminate\Support\Facades\Config;

class VideoField
{
	final public const VIDEO = "video";
	final public const THUMBNAIL = "thumbnail";
	final public const VIDEO_FILE = "file";
	final public const IS_YOUTUBE = "is_youtube";
	final public const ID_YOUTUBE = "id";
	final public const CAPTIONS = "captions";
	final public const TRANSCRIPT = "transcript";
	final public const CONFIG_ACCESSIBILITY = 'medias.accessibility.videos';

	/**
	 * Vidéo
	 */
	public static function make(): Group
	{
		$fields = [
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
				]),
		];

		if (self::accessibilityEnabled()) {
			array_push($fields, ...self::accessibilityFields());
		}

		return Group::make(__("Vidéo", 'horizon-tools'), self::VIDEO)->fields($fields);
	}

	public static function accessibilityEnabled(): bool
	{
		return (bool) Config::get(self::CONFIG_ACCESSIBILITY, false);
	}

	/**
	 * Accessibilité (RGAA 4.1 et 4.3) : facultatifs, à renseigner dès que la vidéo porte une information
	 */
	private static function accessibilityFields(): array
	{
		return [
			File::make(__("Sous-titres (.vtt)", 'horizon-tools'), self::CAPTIONS)
				->acceptedFileTypes(["vtt"])
				->format("array")
				->helperText(__("Obligatoires (accessibilité) si la vidéo contient de la parole. Pour une vidéo YouTube, les sous-titres se gèrent sur YouTube.", 'horizon-tools'))
				->conditionalLogic([
					ConditionalLogic::where(self::IS_YOUTUBE, "!=", 1),
				]),

			WysiwygField::minimal(__("Transcription", 'horizon-tools'), self::TRANSCRIPT)
				->helperText(__("Texte de ce qui est dit et montré dans la vidéo. Obligatoire si la vidéo porte une information (accessibilité) ; inutile pour une vidéo décorative.", 'horizon-tools')),
		];
	}
}
