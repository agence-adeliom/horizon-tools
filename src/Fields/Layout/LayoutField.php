<?php

declare(strict_types=1);

namespace Adeliom\HorizonTools\Fields\Layout;

use Extended\ACF\Fields\Group;
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

    public static function darkMode(): TrueFalse
    {
        return TrueFalse::make(__('Dark mode'), self::FIELD_DARK_MODE)
            ->helperText('Activer le fond sombre pour ce bloc')
            ->stylized();
    }

    public static function mediaPosition(
        array $choices = [
            self::VALUE_MEDIA_POSITION_LEFT => 'À gauche',
            self::VALUE_MEDIA_POSITION_RIGHT => 'À droite',
            self::VALUE_MEDIA_POSITION_BOTTOM => 'En bas',
        ]
    ): RadioButton {
        return RadioButton::make(__('Position du média'), self::FIELD_MEDIA_POSITION)
            ->choices($choices)
            ->default('left')
            ->required();
    }

    public static function margin(array $fields = [self::FIELD_MARGIN_TOP_SIZE, self::FIELD_MARGIN_BOTTOM_SIZE]): Group
    {
        $fieldsGroup = [];

        if (in_array(self::FIELD_MARGIN_TOP_SIZE, $fields)) {
            $fieldsGroup[] = Select::make('Taille de la marge supérieure', self::FIELD_MARGIN_TOP_SIZE)
                ->choices([
                    'none' => 'Nulle',
                    'small' => 'Petite',
                    'large' => 'Grande',
                ])
                ->default('large')
                ->helperText('');
        }

        if (in_array(self::FIELD_MARGIN_BOTTOM_SIZE, $fields)) {
            $fieldsGroup[] = Select::make('Taille de la marge inférieure', self::FIELD_MARGIN_BOTTOM_SIZE)
                ->choices([
                    'none' => 'Nulle',
                    'small' => 'Petite',
                    'large' => 'Grande',
                ])
                ->default('large')
                ->helperText('');
        }

        return Group::make('Marges', self::FIELD_MARGIN)->fields($fieldsGroup);
    }

    public static function mediaRatio(): Group
    {
        $fieldsGroup = [
            TrueFalse::make('Contraindre le ratio du média', self::FIELD_HAS_MEDIA_RATIO)->stylized(),
            RadioButton::make('Ratio', self::FIELD_MEDIA_RATIO_VALUE)
                ->choices([
                    'auto' => 'Automatique',
                    'paysage' => 'Paysage',
                    'portrait' => 'Portrait',
                ])
                ->conditionalLogic([ConditionalLogic::where('has_ratio', '==', 1)]),
        ];

        return Group::make('Ratio du média', self::FIELD_MEDIA_RATIO)->fields($fieldsGroup);
    }
}
