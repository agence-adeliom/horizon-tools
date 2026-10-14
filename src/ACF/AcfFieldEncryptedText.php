<?php

declare(strict_types=1);

namespace Adeliom\HorizonTools\ACF;

use Adeliom\HorizonTools\Services\Encryption\EncryptedValueCodec;

/**
 * Champ ACF texte dont la valeur est chiffrée en base.
 *
 * Chiffré à l’écriture (update_value), déchiffré à la lecture (load_value) : le code
 * du thème manipule toujours le clair via get_field(). Il faut la clé (APP_KEY, dans
 * le .env) ET la base pour retrouver la valeur.
 */
final class AcfFieldEncryptedText extends \acf_field
{
    public const TYPE = 'encrypted_text';

    /**
     * Champs dont la valeur stockée n’a pas pu être déchiffrée pendant la requête,
     * indexés par « postId|clé de champ ».
     *
     * @var array<string, true>
     */
    private static array $unreadable = [];

    public function initialize(): void
    {
        $this->name = self::TYPE;
        $this->label = __('Texte chiffré', 'horizon-tools');
        $this->category = 'basic';
        $this->defaults = [
            'placeholder' => '',
            'revealable' => 0,
        ];
    }

    /**
     * @param array<string, mixed> $field
     */
    public function render_field_settings($field): void
    {
        acf_render_field_setting($field, [
            'label' => __('Valeur affichable', 'horizon-tools'),
            'instructions' => __('Permet d’afficher la valeur en clair dans l’administration.', 'horizon-tools'),
            'name' => 'revealable',
            'type' => 'true_false',
            'ui' => 1,
        ]);
    }

    /**
     * @param array<string, mixed> $field
     */
    public function render_field($field): void
    {
        printf(
            '<input type="password" name="%s[value]" value="" autocomplete="new-password" spellcheck="false" />',
            esc_attr($field['name'])
        );
    }

    /**
     * @param mixed                $value
     * @param int|string           $postId
     * @param array<string, mixed> $field
     */
    public function load_value($value, $postId, $field): mixed
    {
        if (!is_string($value) || $value === '') {
            return $value;
        }

        $codec = EncryptedValueCodec::make();

        if ($codec === null) {
            if (!EncryptedValueCodec::isEncrypted($value)) {
                return $value;
            }

            $this->markUnreadable($postId, $field, 'APP_KEY absente');

            return '';
        }

        $plain = $codec->decrypt($value);

        if ($plain !== null) {
            return $plain;
        }

        $this->markUnreadable($postId, $field, 'illisible avec APP_KEY');

        return '';
    }

    /**
     * Mémorise une valeur illisible et la journalise une seule fois par requête.
     *
     * Vide plutôt qu’une erreur en front, mais jamais en silence : journal PHP
     * (+ avertissement dans le champ en admin).
     *
     * @param int|string           $postId
     * @param array<string, mixed> $field
     */
    private function markUnreadable($postId, array $field, string $reason): void
    {
        $marker = sprintf('%s|%s', (string) $postId, (string) ($field['key'] ?? ''));

        if (isset(self::$unreadable[$marker])) {
            return;
        }

        self::$unreadable[$marker] = true;
        error_log(
            sprintf(
                '[horizon-tools] Champ chiffré « %s » (%s) : %s. Voir `wp acorn encryption:status`.',
                $field['name'] ?? '',
                (string) $postId,
                $reason
            )
        );
    }

    /**
     * @param array<string, mixed> $field
     */
    private function isUnreadable(array $field): bool
    {
        $suffix = '|' . (string) ($field['key'] ?? '');

        foreach (array_keys(self::$unreadable) as $marker) {
            if (str_ends_with($marker, $suffix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param mixed                $value
     * @param int|string           $postId
     * @param array<string, mixed> $field
     */
    public function update_value($value, $postId, $field): mixed
    {
        // Envoi du formulaire d’administration : ['value' => …, 'clear' => '1'].
        if (is_array($value)) {
            if (!empty($value['clear'])) {
                return '';
            }

            $value = (string) ($value['value'] ?? '');

            // Valeur jamais renvoyée au navigateur : un champ laissé vide signifie
            // « conserver ». En mode affichable, le champ est prérempli : vide = effacer.
            if ($value === '' && empty($field['revealable'])) {
                $value = $this->storedValue($postId, $field);
            }
        }

        if (!is_string($value) || $value === '') {
            return $value;
        }

        // Déjà chiffrée (« conserver » sur la valeur stockée) : rien à refaire, même sans clé.
        if (EncryptedValueCodec::isEncrypted($value)) {
            return $value;
        }

        $codec = EncryptedValueCodec::make();

        if ($codec === null) {
            // Dernier rempart (validate_value bloque déjà le formulaire) : jamais de clair en base.
            throw new \RuntimeException(sprintf('APP_KEY absente : enregistrement du champ chiffré « %s » refusé.', $field['name'] ?? ''));
        }

        return $codec->encrypt($value);
    }

    /**
     * Valeur brute actuellement en base (chiffrée), sans passer par load_value.
     *
     * @param int|string           $postId
     * @param array<string, mixed> $field
     */
    private function storedValue($postId, array $field): string
    {
        $stored = acf_get_metadata_by_field($postId, $field);

        return is_string($stored) ? $stored : '';
    }
}
