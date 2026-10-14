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

        $this->registerEncryptedTextHooks();
    }

    public function registerFieldTypes(): void
    {
        // AcfFieldTextWithTags s’enregistre lui-même via le constructeur parent acf_field.
        if (TextService::areTextReplacementsEnabled()) {
            new AcfFieldTextWithTags();
        }

        // Le type chiffré passe par le registre d’ACF : acf_get_field_type('encrypted_text') le retrouve.
        acf_register_field_type(AcfFieldEncryptedText::class);
    }

    /**
     * Hooks propres au champ « Texte chiffré ». Seul l’enregistrement est fait ici : les
     * callbacks ne sont déclenchés que par ACF (ou garde function_exists pour widget_block),
     * aucun n’a besoin qu’ACF soit déjà chargé au moment de l’appel.
     */
    private function registerEncryptedTextHooks(): void
    {
        // Blocs ACF : valeurs stockées dans les attributs du bloc (post_content), hors update_value
        add_filter('acf/pre_save_block', [EncryptedBlockData::class, 'encryptAttributes'], 10, 1);
        // Widgets « Bloc » : enregistrés sans content_save_pre
        add_filter('pre_update_option_widget_block', [EncryptedBlockData::class, 'encryptWidgets'], 10, 1);
        // Formulaire de bloc : une valeur chiffrée illisible est conservée au lieu d’être effacée
        add_filter('acf/pre_render_fields', [EncryptedBlockData::class, 'beginFieldsRender'], 10, 2);
        add_action('acf/render_fields', [EncryptedBlockData::class, 'endFieldsRender'], 10, 0);
        // API REST : jamais de valeur déchiffrée (priorité maximale : dernier mot après tout autre filtre)
        add_filter('acf/rest/format_value_for_rest', [AcfFieldEncryptedText::class, 'hideFromRest'], PHP_INT_MAX, 3);
    }
}
