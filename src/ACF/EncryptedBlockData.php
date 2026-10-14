<?php

declare(strict_types=1);

namespace Adeliom\HorizonTools\ACF;

use Adeliom\HorizonTools\Services\Encryption\EncryptedValueCodec;

/**
 * Chiffre les champs « Texte chiffré » d’un bloc ACF stockés dans ses attributs (post_content).
 *
 * Mécanisme ACF 6.8.10 (pro/blocks.php) : acf_parse_save_blocks (l. 1754), branché sur
 * content_save_pre en priorité 5, décode chaque commentaire de bloc après stripslashes ;
 * acf_parse_save_blocks_callback (l. 1777) convertit ensuite `data` au format meta par
 * acf_setup_meta (l. 1805) puis applique le filtre acf/pre_save_block (l. 1816).
 *
 * - Données au format « requête » (`clé de champ => valeur`, ce que produit acf.serialize()
 *   du formulaire de bloc : `{ field_xxx: { value, clear } }`) : ACF_Local_Meta::add() les
 *   convertit en simulant un enregistrement (includes/local-meta.php l. 62 et 106,
 *   acf_update_values) ; update_value du champ, qui délègue à encryptValue(), a déjà chiffré.
 * - Données déjà au format meta (`nom => valeur`, `_nom => clé de champ`), par exemple un
 *   bloc réenregistré sans modification ou inséré par le code : acf_setup_meta les renvoie
 *   telles quelles, d’où ce filtre.
 * - Bloc `use_post_meta` : `data` est retiré avant le filtre ; les valeurs passent par
 *   acf_save_post puis update_value.
 */
final class EncryptedBlockData
{
    /**
     * Filtre acf/pre_save_block : chiffre les valeurs des champs chiffrés de `data`.
     *
     * Ne lève jamais d’exception (elle bloquerait l’enregistrement de toute la page) et ne
     * laisse jamais de clair.
     */
    public static function encryptAttributes(mixed $attrs): mixed
    {
        if (!is_array($attrs) || !isset($attrs['data']) || !is_array($attrs['data'])) {
            return $attrs;
        }

        foreach ($attrs['data'] as $reference => $key) {
            if (!is_string($reference) || !str_starts_with($reference, '_') || !is_string($key) || !acf_is_field_key($key)) {
                continue;
            }

            $name = substr($reference, 1);

            if (!array_key_exists($name, $attrs['data'])) {
                continue;
            }

            $field = acf_get_field($key);

            if (!is_array($field) || ($field['type'] ?? '') !== AcfFieldEncryptedText::TYPE) {
                continue;
            }

            $attrs['data'][$name] = self::encryptValue($attrs['data'][$name], $name);
        }

        return $attrs;
    }

    /**
     * Valeur à stocker dans les attributs du bloc : chiffrée, ou vide.
     *
     * Pas de wp_unslash : acf_parse_save_blocks a déjà retiré les barres obliques de
     * WordPress (stripslashes, pro/blocks.php l. 1761), comme le rendu par AJAX (l. 1594).
     */
    public static function encryptValue(mixed $value, string $name): string
    {
        // Formulaire du bloc : ['value' => …, 'clear' => '1'].
        if (is_array($value)) {
            $value = empty($value['clear']) ? $value['value'] ?? '' : '';
        }

        if (!is_scalar($value)) {
            return '';
        }

        $value = (string) $value;

        if ($value === '' || EncryptedValueCodec::isEncrypted($value)) {
            return $value;
        }

        $codec = EncryptedValueCodec::make();

        if ($codec === null) {
            error_log(sprintf('[horizon-tools] APP_KEY absente : valeur du champ chiffré « %s » non enregistrée dans le bloc.', $name));

            return '';
        }

        try {
            return $codec->encrypt($value);
        } catch (\Throwable $exception) {
            error_log(
                sprintf(
                    '[horizon-tools] Chiffrement impossible : valeur du champ chiffré « %s » non enregistrée dans le bloc (%s).',
                    $name,
                    $exception->getMessage()
                )
            );

            return '';
        }
    }
}
