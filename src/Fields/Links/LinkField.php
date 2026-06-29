<?php

declare(strict_types=1);

namespace Adeliom\HorizonTools\Fields\Links;

use Adeliom\HorizonTools\Fields\Text\IconField;
use Adeliom\HorizonTools\Services\SeoService;
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
    public const FIELD_OBFUSCATE = 'obfuscate';

    public static function make(string $label = 'Lien', ?string $name = self::FIELD_LINK): Group
    {
        $withObfuscation = SeoService::isObfuscationEnabled();

        return Group::make(__($label, 'horizon-tools'), $name)->fields(
            array_filter([
                ButtonGroup::make(__('Type de lien', 'horizon-tools'), self::FIELD_TYPE)->choices([
                    self::VALUE_TYPE_INTERNAL => __('Interne', 'horizon-tools'),
                    self::VALUE_TYPE_EXTERNAL => __('Externe', 'horizon-tools'),
                ]),
                PostObject::make(__('Page', 'horizon-tools'), self::FIELD_POST)
                    ->helperText(__('Sélectionner une page', 'horizon-tools'))
                    ->required()
                    ->conditionalLogic([ConditionalLogic::where(self::FIELD_TYPE, '==', self::VALUE_TYPE_INTERNAL)])
                    ->wrapper(['width' => 50]),
                Text::make(__('Texte', 'horizon-tools'), self::FIELD_POST_LABEL)
                    ->helperText(__('Si renseigné, permet de remplacer le titre de la page', 'horizon-tools'))
                    ->placeholder(__('Titre du lien', 'horizon-tools'))
                    ->conditionalLogic([ConditionalLogic::where(self::FIELD_TYPE, '==', self::VALUE_TYPE_INTERNAL)])
                    ->wrapper(['width' => 50]),
                TrueFalse::make(__('Ouvrir dans un nouvel onglet', 'horizon-tools'), self::FIELD_IS_TARGET_BLANK)
                    ->stylized()
                    ->conditionalLogic([ConditionalLogic::where(self::FIELD_TYPE, '==', self::VALUE_TYPE_INTERNAL)])
                    ->wrapper(['width' => $withObfuscation ? 50 : 100]),
                $withObfuscation
                    ? TrueFalse::make(__('Obfusquer le lien', 'horizon-tools'), self::FIELD_OBFUSCATE)
                        ->stylized()
                        ->wrapper(['width' => 50])
                    : null,
                Link::make(__('Lien', 'horizon-tools'), self::FIELD_EXTERNAL_LINK)
                    ->required()
                    ->conditionalLogic([ConditionalLogic::where(self::FIELD_TYPE, '==', self::VALUE_TYPE_EXTERNAL)]),
                IconField::make(__('Icône', 'horizon-tools'), self::FIELD_ICON)->format('object'),
            ])
        );
    }
}
