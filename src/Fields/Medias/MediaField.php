<?php

declare(strict_types=1);

namespace Adeliom\HorizonTools\Fields\Medias;

use Extended\ACF\ConditionalLogic;
use Extended\ACF\Fields\Group;
use Extended\ACF\Fields\RadioButton;
use Extended\ACF\Fields\Select;

class MediaField
{
    final public const HAS_IMAGE = "image";
    final public const HAS_VIDEO = "video";
    final public const IMAGE_POSITION = "imagePosition";
    final public const IMAGE_POSITION_TOP = "top";
    final public const IMAGE_POSITION_CENTER = "center";
    final public const IMAGE_POSITION_BOTTOM = "bottom";

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
                ->required(),
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

    public static function image(string $instructions = "", bool $isImagePosition = false): Group
    {

        return Group::make("Média", self::MEDIA)
            ->helperText($instructions)
            ->fields([
                $isImagePosition ? self::imagePosition() : "",
                ImageField::make()
                    ->conditionalLogic([
                        ConditionalLogic::where(self::TYPE, "==", self::HAS_IMAGE),
                    ]),
            ]);
    }

    public static function video(string $instructions = ""): Group
    {
        return Group::make("Média", self::MEDIA)
            ->helperText($instructions)
            ->fields([
                VideoField::make()->conditionalLogic([
                    ConditionalLogic::where(self::TYPE, "==", self::HAS_VIDEO),
                ]),
            ]);
    }

    public static function imagePosition()
    {
        return RadioButton::make("Position de l'image", self::IMAGE_POSITION)
            ->choices([
                self::IMAGE_POSITION_TOP    => 'Ferrée en haut',
                self::IMAGE_POSITION_CENTER => 'Centrée',
                self::IMAGE_POSITION_BOTTOM => 'Ferrée en bas',
            ])
            ->default(  self::IMAGE_POSITION_CENTER);
    }
}