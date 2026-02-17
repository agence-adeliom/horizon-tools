<?php

declare(strict_types=1);

namespace Adeliom\HorizonTools\Hooks;

use Adeliom\HorizonTools\ACF\AcfFieldTextWithTags;
use Adeliom\HorizonTools\Services\TextService;

/**
 * Enregistre les types de champs ACF personnalisés.
 */
class DefaultAcfHooks extends AbstractHook
{
    public function init(): void
    {
        if (TextService::areTextReplacementsEnabled()) {
            add_action('acf/include_field_types', [$this, 'addTextWithTagsField']);
        }
    }

    public function addTextWithTagsField(): void
    {
        // The field registers itself via the acf_field parent constructor
        new AcfFieldTextWithTags();
    }
}
