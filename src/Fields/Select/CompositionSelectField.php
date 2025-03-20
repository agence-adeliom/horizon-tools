<?php

declare(strict_types=1);

namespace Adeliom\HorizonTools\Fields\Select;

use Adeliom\HorizonTools\Services\CompositionService;
use Extended\ACF\Fields\Select;

class CompositionSelectField
{
    final public const LABEL = 'Composition';
    final public const NAME = 'composition';

    public static function make(string $label = self::LABEL, ?string $name = self::NAME): Select
    {
        return Select::make(__($label), $name)->choices(CompositionService::getCompositionChoices())->stylized();
    }
}
