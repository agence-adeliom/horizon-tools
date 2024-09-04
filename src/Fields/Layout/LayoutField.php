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
    final public const MEDIA_POSITION = "media_position";
    final public const DARK_MODE = "dark_mode";

    final public const MARGIN = "margin";

    final public const MARGIN_SIZES = "sizes";
    final public const MARGIN_TOP_SIZE = "top_size";
    final public const MARGIN_BOTTOM_SIZE = "bottom_size";
    final public const MARGIN_TOP_REMOVE = "top_remove";
    final public const MARGIN_BOTTOM_REMOVE = "bottom_remove";

    final public const MEDIA_RATIO = "media_ratio";
    final public const HAS_MEDIA_RATIO = "has_ratio";
    final public const MEDIA_RATIO_VALUE = "ratio";

    public static function darkMode(): TrueFalse
    {
        return TrueFalse::make(__('Dark mode'), self::DARK_MODE)
            ->helperText('Activer le fond sombre pour ce bloc')
            ->stylized();
    }

    public static function mediaPosition(array $choices = [
        'left'   => 'À gauche',
        'right'  => 'À droite'
    ]): RadioButton
    {
        return RadioButton::make(__('Position du média'), self::MEDIA_POSITION)
            ->choices($choices)
            ->default("left")
            ->required();
    }

    public static function margin(array $fields = [
        self::MARGIN_TOP_SIZE,
        self::MARGIN_BOTTOM_SIZE,
    ]): Group
    {

        $fieldsGroup = [];

        if (in_array(self::MARGIN_TOP_SIZE, $fields)) {
            $fieldsGroup[] = Select::make("Taille de la marge supérieure", self::MARGIN_TOP_SIZE)
                ->choices([
                    "none" => "Nulle",
                    "small" => "Petite",
                    "large" => "Grande"
                ])
                ->default("large")
                ->helperText("");
        }

        if (in_array(self::MARGIN_BOTTOM_SIZE, $fields)) {
            $fieldsGroup[] = Select::make("Taille de la marge inférieure", self::MARGIN_BOTTOM_SIZE)
                ->choices([
                    "none" => "Nulle",
                    "small" => "Petite",
                    "large" => "Grande"
                ])
                ->default("large")
                ->helperText("");
        }

        return Group::make("Marges", self::MARGIN)->fields($fieldsGroup);
    }

    public static function mediaRatio(): Group
    {
        $fieldsGroup = [
            TrueFalse::make("Contraindre le ratio du média", self::HAS_MEDIA_RATIO)
                ->stylized(),
            RadioButton::make("Ratio", self::MEDIA_RATIO_VALUE)->choices([
                "auto" => "Automatique",
                "paysage" => "Paysage",
                "portrait" => "Portrait"
            ])->conditionalLogic([
                ConditionalLogic::where("has_ratio", "==", 1)
            ]),
        ];


        return Group::make("Ratio du média", self::MEDIA_RATIO)->fields($fieldsGroup);
    }
}
