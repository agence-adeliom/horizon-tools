<?php

declare(strict_types=1);

namespace Adeliom\HorizonTools\Fields\Medias;

use Extended\ACF\ConditionalLogic;
use Extended\ACF\Fields\Group;
use Extended\ACF\Fields\RadioButton;
use Extended\ACF\Fields\Select;

class MediaField
{
    final public const HAS_IMAGE = 'image';
    final public const HAS_VIDEO = 'video';
    final public const IMAGE_POSITION = 'imagePosition';
    final public const IMAGE_POSITION_TOP = 'top';
    final public const IMAGE_POSITION_CENTER = 'center';
    final public const IMAGE_POSITION_BOTTOM = 'bottom';

    final public const MEDIA = 'media';
    final public const TYPE = 'type';

    public static function make(
        string $instructions = '',
        array $includes = [self::HAS_IMAGE, self::HAS_VIDEO],
        string $name = self::MEDIA
    ): Group {
        $choices = [];

        $hasImage = in_array(self::HAS_IMAGE, $includes, true);
        $hasVideo = in_array(self::HAS_VIDEO, $includes, true);

        if ($hasImage) {
            $choices[self::HAS_IMAGE] = __('Image', 'horizon-tools');
        }

        if ($hasVideo) {
            $choices[self::HAS_VIDEO] = __('Vidéo', 'horizon-tools');
        }

        $fields = [
            Select::make(__('Type', 'horizon-tools'), self::TYPE)
                ->choices($choices)
                ->helperText(__('Choisir le type de média', 'horizon-tools'))
                ->stylized()
                ->required(),
        ];

        if ($hasImage) {
            $imageField = ImageField::make()->conditionalLogic([ConditionalLogic::where(self::TYPE, '==', self::HAS_IMAGE)]);

            $fields[] = $imageField;
        }

        if ($hasVideo) {
            $videoField = VideoField::make()->conditionalLogic([ConditionalLogic::where(self::TYPE, '==', self::HAS_VIDEO)]);

            $fields[] = $videoField;
        }

        return Group::make(__('Média', 'horizon-tools'), $name)->helperText($instructions)->fields($fields);
    }

    public static function image(string $instructions = '', bool $isImagePosition = false, string $name = self::MEDIA): Group
    {
        return Group::make(__('Média', 'horizon-tools'), $name)
            ->helperText($instructions)
            ->fields(
                array_filter([
                    $isImagePosition ? self::imagePosition() : null,
                    ImageField::make()->conditionalLogic([ConditionalLogic::where(self::TYPE, '==', self::HAS_IMAGE)]),
                ])
            );
    }

    public static function video(string $instructions = '', string $name = self::MEDIA): Group
    {
        return Group::make(__('Média', 'horizon-tools'), $name)
            ->helperText($instructions)
            ->fields([VideoField::make()->conditionalLogic([ConditionalLogic::where(self::TYPE, '==', self::HAS_VIDEO)])]);
    }

    public static function imagePosition()
    {
        return RadioButton::make(__("Position de l'image", 'horizon-tools'), self::IMAGE_POSITION)
            ->choices([
                self::IMAGE_POSITION_TOP => __('Ferrée en haut', 'horizon-tools'),
                self::IMAGE_POSITION_CENTER => __('Centrée', 'horizon-tools'),
                self::IMAGE_POSITION_BOTTOM => __('Ferrée en bas', 'horizon-tools'),
            ])
            ->default(self::IMAGE_POSITION_CENTER);
    }
}
