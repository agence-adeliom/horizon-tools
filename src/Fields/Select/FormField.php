<?php

declare(strict_types=1);

namespace Adeliom\HorizonTools\Fields\Select;

use Adeliom\HorizonTools\Services\FormService;
use Extended\ACF\Fields\Select;

class FormField
{
    public const FORM_SELECT = 'form_id';

    public static function selectGravityForm(string $label = 'Formulaire', string $name = self::FORM_SELECT): Select
    {
        return Select::make($label, $name)->stylized()->nullable()->choices(FormService::getAllFormChoices());
    }
}
