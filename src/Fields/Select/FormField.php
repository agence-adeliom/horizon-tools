<?php

declare(strict_types=1);

namespace Adeliom\HorizonTools\Fields\Select;

use Adeliom\HorizonTools\Services\FormService;
use Extended\ACF\Fields\Select;

class FormField
{
    public const FORM_SELECT = 'form_id';

    public static function selectGF(): Select
    {
        return Select::make('Formulaire', self::FORM_SELECT)
            ->stylized()
            ->nullable()
            ->choices(FormService::getAllFormChoices());
    }
}
