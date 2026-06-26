<?php

declare(strict_types=1);

namespace Adeliom\HorizonTools\Fields\Buttons;

use Adeliom\HorizonTools\Fields\Links\LinkField;
use Extended\ACF\Fields\Group;
use Extended\ACF\Fields\Link;
use Extended\ACF\Fields\Select;

class ButtonField
{
    public const BUTTON = 'button';
    public const BUTTON_TYPE = 'type';
    public const BUTTON_LINK = 'link';

    public const BUTTONS = 'buttons';
    public const BUTTON_ONE = 'one';
    public const BUTTON_TWO = 'two';

    public static function make(
        string $label = 'Bouton',
        string|null $name = self::BUTTON,
        bool $withType = false,
        bool $withInternalExternal = false
    ): Group {
        if ($withInternalExternal) {
            return LinkField::make(label: $label, name: $name);
        }

        return Group::make(__('Bouton', 'horizon-tools'), $name)->fields(
            array_filter([$withType ? self::typeField() : null, Link::make(__($label, 'horizon-tools'), self::BUTTON_LINK)])
        );
    }

    public static function types(
        string $title = 'Bouton',
        string|null $typeInstructions = '',
        string|null $name = self::BUTTON,
        bool $withInternalExternal = false
    ): Group {
        return Group::make(__($title, 'horizon-tools'), $name)->fields([
            self::typeField($typeInstructions),
            $withInternalExternal ? LinkField::make(name: self::BUTTON_LINK) : Link::make(__('Lien', 'horizon-tools'), self::BUTTON_LINK),
        ]);
    }

    private static function typeField(?string $typeInstructions = null): Select
    {
        $field = Select::make(__('Type', 'horizon-tools'), self::BUTTON_TYPE)
            ->choices([
                'primary' => __('Primaire', 'horizon-tools'),
                'secondary' => __('Secondaire', 'horizon-tools'),
                'tertiary' => __('Tertiaire', 'horizon-tools'),
            ])
            ->default('primary')
            ->stylized();

        if (!empty($typeInstructions)) {
            $field->helperText($typeInstructions);
        }

        return $field;
    }

    /**
     * Groupe de deux boutons
     */
    public static function group(bool $withType = false, bool $withInternalExternal = false, string $name = self::BUTTONS): Group
    {
        $fields = [
            self::make(label: __('Bouton principal', 'horizon-tools'), name: self::BUTTON_ONE, withInternalExternal: $withInternalExternal),
            self::make(label: __('Bouton secondaire', 'horizon-tools'), name: self::BUTTON_TWO, withInternalExternal: $withInternalExternal),
        ];

        if ($withType) {
            $fields = [
                self::types(title: __('Bouton principal', 'horizon-tools'), name: self::BUTTON_ONE, withInternalExternal: $withInternalExternal),
                self::types(title: __('Bouton secondaire', 'horizon-tools'), name: self::BUTTON_TWO, withInternalExternal: $withInternalExternal),
            ];
        }

        return Group::make(__('Boutons', 'horizon-tools'), $name)->fields($fields);
    }
}
