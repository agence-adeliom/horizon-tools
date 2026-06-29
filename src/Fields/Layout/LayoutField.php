<?php

declare(strict_types=1);

namespace Adeliom\HorizonTools\Fields\Layout;

use Extended\ACF\Fields\Group;
use Extended\ACF\Fields\Image;
use Extended\ACF\Fields\RadioButton;
use Extended\ACF\Fields\Select;
use Extended\ACF\Fields\TrueFalse;
use Extended\ACF\ConditionalLogic;

class LayoutField
{
    public const FIELD_MEDIA_POSITION = 'media_position';
    public const VALUE_MEDIA_POSITION_LEFT = 'left';
    public const VALUE_MEDIA_POSITION_RIGHT = 'right';
    public const VALUE_MEDIA_POSITION_BOTTOM = 'bottom';

    public const FIELD_DARK_MODE = 'dark_mode';

    public const FIELD_MARGIN = 'margin';

    public const FIELD_MARGIN_TOP_SIZE = 'top_size';
    public const FIELD_MARGIN_BOTTOM_SIZE = 'bottom_size';

    public const FIELD_MEDIA_RATIO = 'media_ratio';
    public const FIELD_HAS_MEDIA_RATIO = 'has_ratio';
    public const FIELD_MEDIA_RATIO_VALUE = 'ratio';

    public const FIELD_BG_GROUP = 'bg';
    public const FIELD_BG_TYPE = 'bg_type';
    public const FIELD_BG_COLOR = 'bg_color';
    public const FIELD_BG_COLOR_SELECTION = 'bg_color_selection';
    public const FIELD_BG_IMAGE = 'bg_image';

    public static function darkMode(): TrueFalse
    {
        return TrueFalse::make(__('Dark mode', 'horizon-tools'), self::FIELD_DARK_MODE)
            ->helperText(__('Activer le fond sombre pour ce bloc', 'horizon-tools'))
            ->stylized();
    }

    public static function mediaPosition(array $choices = []): RadioButton
    {
        if (empty($choices)) {
            $choices = [
                self::VALUE_MEDIA_POSITION_LEFT   => __('À gauche', 'horizon-tools'),
                self::VALUE_MEDIA_POSITION_RIGHT  => __('À droite', 'horizon-tools'),
                self::VALUE_MEDIA_POSITION_BOTTOM => __('En bas', 'horizon-tools'),
            ];
        }

        return RadioButton::make(__('Position du média', 'horizon-tools'), self::FIELD_MEDIA_POSITION)
            ->choices($choices)
            ->default('left')
            ->required();
    }

    public static function margin(array $fields = [
        self::FIELD_MARGIN_TOP_SIZE,
        self::FIELD_MARGIN_BOTTOM_SIZE,
    ]): Group
    {
        $fieldsGroup = [];

        if (in_array(self::FIELD_MARGIN_TOP_SIZE, $fields)) {
            $fieldsGroup[] = Select::make(__('Taille de la marge supérieure', 'horizon-tools'), self::FIELD_MARGIN_TOP_SIZE)
                ->choices([
                    'none'  => __('Nulle', 'horizon-tools'),
                    'small' => __('Petite', 'horizon-tools'),
                    'large' => __('Grande', 'horizon-tools'),
                ])
                ->default('large')
                ->helperText('');
        }

        if (in_array(self::FIELD_MARGIN_BOTTOM_SIZE, $fields)) {
            $fieldsGroup[] = Select::make(__('Taille de la marge inférieure', 'horizon-tools'), self::FIELD_MARGIN_BOTTOM_SIZE)
                ->choices([
                    'none'  => __('Nulle', 'horizon-tools'),
                    'small' => __('Petite', 'horizon-tools'),
                    'large' => __('Grande', 'horizon-tools'),
                ])
                ->default('large')
                ->helperText('');
        }

        return Group::make(__('Marges', 'horizon-tools'), self::FIELD_MARGIN)->fields($fieldsGroup);
    }

    public static function mediaRatio(): Group
    {
        $fieldsGroup = [
            TrueFalse::make(__('Contraindre le ratio du média', 'horizon-tools'), self::FIELD_HAS_MEDIA_RATIO)->stylized(),
            RadioButton::make(__('Ratio', 'horizon-tools'), self::FIELD_MEDIA_RATIO_VALUE)
                ->choices([
                    'auto'     => __('Automatique', 'horizon-tools'),
                    'paysage'  => __('Paysage', 'horizon-tools'),
                    'portrait' => __('Portrait', 'horizon-tools'),
                ])
                ->conditionalLogic([ConditionalLogic::where('has_ratio', '==', 1)]),
        ];

        return Group::make(__('Ratio du média', 'horizon-tools'), self::FIELD_MEDIA_RATIO)->fields($fieldsGroup);
    }

    public static function choicesBackgroundType(bool $allowColor = true, bool $allowImage = true): Group
    {
        $choices = ['none' => __('Aucun', 'horizon-tools')];
        if ($allowColor) {
            $choices[self::FIELD_BG_COLOR] = __('Fond de couleur', 'horizon-tools');
        }
        if ($allowImage) {
            $choices[self::FIELD_BG_IMAGE] = __('Image de fond', 'horizon-tools');
        }

        $fieldsGroup = [
            RadioButton::make(__('Type de fond', 'horizon-tools'), self::FIELD_BG_TYPE)
                ->choices($choices),
            self::backgroundColorSelection()
                ->conditionalLogic([ConditionalLogic::where(self::FIELD_BG_TYPE, '==', self::FIELD_BG_COLOR)]),
            self::backgroundImage()
                ->conditionalLogic([ConditionalLogic::where(self::FIELD_BG_TYPE, '==', self::FIELD_BG_IMAGE)]),
        ];

        return Group::make(__('Fond', 'horizon-tools'), self::FIELD_BG_GROUP)->fields($fieldsGroup);
    }

    public static function backgroundImage()
    {
        return Image::make(__('Image de fond', 'horizon-tools'), self::FIELD_BG_IMAGE);
    }

    public static function backgroundColorSelection(?array $choices = null): Select
    {
        if ($choices === null) {
            $choices = self::getColorChoices();
        }
        return Select::make(__('Couleur de fond', 'horizon-tools'), self::FIELD_BG_COLOR)
            ->choices($choices);
    }

    private static function getColorChoices(): array
    {
        return [
            'bg-color-02-50'  => __('Principale', 'horizon-tools'),
            'bg-color-04-200' => __('Secondaire', 'horizon-tools'),
        ];
    }
}
