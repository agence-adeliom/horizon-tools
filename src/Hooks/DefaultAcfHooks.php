<?php

declare(strict_types=1);

namespace Adeliom\HorizonTools\Hooks;

use Adeliom\HorizonTools\ACF\AcfFieldEncryptedText;
use Adeliom\HorizonTools\ACF\AcfFieldTextWithTags;
use Adeliom\HorizonTools\ACF\EncryptedBlockData;
use Adeliom\HorizonTools\Services\TextService;

/**
 * Enregistre les types de champs ACF personnalisés.
 */
class DefaultAcfHooks extends AbstractHook
{
    public function init(): void
    {
        if (did_action('acf/include_field_types')) {
            $this->registerFieldTypes();
        } else {
            add_action('acf/include_field_types', [$this, 'registerFieldTypes']);
        }
    }

    public function registerFieldTypes(): void
    {
        // Chaque type s’enregistre lui-même via le constructeur parent acf_field
        if (TextService::areTextReplacementsEnabled()) {
            new AcfFieldTextWithTags();
        }

        acf_register_field_type(AcfFieldEncryptedText::class);
        // Blocs ACF : valeurs stockées dans les attributs du bloc (post_content), hors update_value
        add_filter('acf/pre_save_block', [EncryptedBlockData::class, 'encryptAttributes'], 10, 1);
    }
}
