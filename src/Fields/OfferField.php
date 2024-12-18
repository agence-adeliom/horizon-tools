<?php

declare(strict_types=1);

namespace Adeliom\HorizonTools\Fields;

use Adeliom\HorizonTools\Fields\Text\UptitleField;
use Adeliom\HorizonTools\Fields\Text\WysiwygField;
use Extended\ACF\ConditionalLogic;
use Extended\ACF\Fields\Group;
use Extended\ACF\Fields\TrueFalse;

class OfferField
{
    final public const OFFER = 'offer';
    final public const OFFER_ENABLE = 'enable';

    public static function make(string $label = "Bandeau d'offre", string|null $name = self::OFFER): Group
    {
        return Group::make("Bandeau d'offre", $name)->fields([
            TrueFalse::make('Activer', self::OFFER_ENABLE)->stylized(),

            UptitleField::make()
                ->required()
                ->conditionalLogic([ConditionalLogic::where(self::OFFER_ENABLE, '==', '1')]),

            WysiwygField::minimal()
                ->required()
                ->conditionalLogic([ConditionalLogic::where(self::OFFER_ENABLE, '==', '1')]),
        ]);
    }
}
