<?php

declare(strict_types=1);

namespace Adeliom\HorizonTools\Fields\Links;

use Adeliom\HorizonTools\Fields\Text\IconField;
use Extended\ACF\ConditionalLogic;
use Extended\ACF\Fields\ButtonGroup;
use Extended\ACF\Fields\Group;
use Extended\ACF\Fields\Link;
use Extended\ACF\Fields\PostObject;
use Extended\ACF\Fields\Text;
use Extended\ACF\Fields\TrueFalse;

class LinkField
{
    public const FIELD_LINK = 'link';
    public const FIELD_TYPE = 'type';
    public const VALUE_TYPE_INTERNAL = 'internal';
    public const VALUE_TYPE_EXTERNAL = 'external';

    public const FIELD_POST = 'post';
    public const FIELD_POST_LABEL = 'label';
    public const FIELD_EXTERNAL_LINK = 'link';
    public const FIELD_IS_TARGET_BLANK = 'is-target-blank';
    public const FIELD_ICON = 'icon';

    public static function make(string $label = 'Lien', ?string $name = self::FIELD_LINK): Group
    {
        return Group::make(__($label), $name)->fields([
            ButtonGroup::make(__('Type de lien'), self::FIELD_TYPE)->choices([
                self::VALUE_TYPE_INTERNAL => __('Interne'),
                self::VALUE_TYPE_EXTERNAL => __('Externe'),
            ]),
            PostObject::make(__('Page'), self::FIELD_POST)
                ->helperText(__('Sélectionner une page'))
                ->required()
                ->conditionalLogic([ConditionalLogic::where(self::FIELD_TYPE, '==', self::VALUE_TYPE_INTERNAL)])
                ->wrapper(['width' => 50]),
            Text::make(__('Texte'), self::FIELD_POST_LABEL)
                ->helperText(__('Si renseigné, permet de remplacer le titre de la page'))
                ->placeholder(__('Titre du lien'))
                ->conditionalLogic([ConditionalLogic::where(self::FIELD_TYPE, '==', self::VALUE_TYPE_INTERNAL)])
                ->wrapper(['width' => 50]),
            TrueFalse::make(__('Ouvrir dans un nouvel onglet'), self::FIELD_IS_TARGET_BLANK)
                ->stylized()
                ->conditionalLogic([ConditionalLogic::where(self::FIELD_TYPE, '==', self::VALUE_TYPE_INTERNAL)]),
            Link::make(__('Lien'), self::FIELD_EXTERNAL_LINK)
                ->required()
                ->conditionalLogic([ConditionalLogic::where(self::FIELD_TYPE, '==', self::VALUE_TYPE_EXTERNAL)]),
            IconField::make(__('Icône'), self::FIELD_ICON)->format('object'),
        ]);
    }
}
