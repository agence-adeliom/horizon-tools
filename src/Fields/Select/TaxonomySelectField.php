<?php

declare(strict_types=1);

namespace Adeliom\HorizonTools\Fields\Select;

use Adeliom\HorizonTools\Services\PostService;
use Extended\ACF\Fields\Select;

class TaxonomySelectField
{
    final public const LABEL = 'Taxonomie';
    final public const NAME = 'taxonomy';

    public static function make(string $postType, string $label = self::LABEL, ?string $name = self::NAME, array $excluded = []): Select
    {
        $choices = PostService::getAllAssociatedTaxonomies(postType: $postType, excluded: $excluded);

        return Select::make(__($label), $name)->choices($choices)->stylized();
    }
}
