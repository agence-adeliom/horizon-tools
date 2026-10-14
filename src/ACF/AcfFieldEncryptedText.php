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
        $revealable = !empty($field['revealable']);
        $hasValue = is_string($field['value']) && $field['value'] !== '';
        $unreadable = $this->isUnreadable($field);

        if (EncryptedValueCodec::make() === null) {
            printf(
                '<p class="acf-notice -error"><strong>%s</strong></p>',
                esc_html__('APP_KEY absente : ce champ chiffré ne peut être ni lu ni enregistré.', 'horizon-tools')
            );
        } elseif ($unreadable) {
            printf(
                '<p class="acf-notice -warning hz-encrypted-text__unreadable"><strong>%s</strong></p>',
                esc_html__(
                    'Une valeur est enregistrée mais illisible avec la clé actuelle (APP_KEY changée ou base importée d’un autre environnement). Ressaisir la valeur, ou restaurer l’ancienne clé.',
                    'horizon-tools'
                )
            );
        }

        $placeholder =
            $hasValue && !$revealable
                ? __('•••••••• (valeur enregistrée — laisser vide pour la conserver)', 'horizon-tools')
                : (string) $field['placeholder'];
        ?>
        <div class="hz-encrypted-text" style="display:flex;gap:6px;align-items:center;">
            <input
                type="password"
                id="<?php echo esc_attr($field['id'] ?? ''); ?>"
                name="<?php echo esc_attr($field['name']); ?>[value]"
                value="<?php echo $revealable ? esc_attr((string) $field['value']) : ''; ?>"
                placeholder="<?php echo esc_attr($placeholder); ?>"
                autocomplete="new-password"
                spellcheck="false"
                <?php disabled(!empty($field['disabled'])); ?>
                style="flex:1;"
            />
            <?php if ($revealable): ?>
                <button type="button" class="button hz-encrypted-text__toggle" aria-pressed="false"><?php esc_html_e(
                    'Afficher',
                    'horizon-tools'
                ); ?></button>
            <?php endif; ?>
        </div>
        <?php if (($hasValue && !$revealable) || $unreadable): ?>
            <label style="display:inline-block;margin-top:6px;">
                <input type="checkbox" name="<?php echo esc_attr($field['name']); ?>[clear]" value="1" />
                <?php esc_html_e('Effacer la valeur enregistrée', 'horizon-tools'); ?>
            </label>
        <?php endif; ?>
        <script>
        if (!window._hzEncryptedTextInit) {
            window._hzEncryptedTextInit = true;
            document.addEventListener('click', function (e) {
                var button = e.target.closest('.hz-encrypted-text__toggle');
                if (!button) return;
                var input = button.parentElement.querySelector('input');
                var reveal = input.type === 'password';
                input.type = reveal ? 'text' : 'password';
                button.setAttribute('aria-pressed', reveal ? 'true' : 'false');
                button.textContent = reveal ? <?php echo wp_json_encode(
                    __('Masquer', 'horizon-tools')
                ); ?> : <?php echo wp_json_encode(__('Afficher', 'horizon-tools')); ?>;
            });
        }
        </script>
        <?php
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

            // ACF reçoit $_POST échappé par WordPress (magic quotes) : on ne le déséchappe pas
            // ailleurs pour la valeur en clair, sinon elle serait chiffrée avec ses barres obliques.
            $value = wp_unslash((string) ($value['value'] ?? ''));

            // Valeur jamais renvoyée au navigateur : un champ laissé vide signifie
            // « conserver ». En mode affichable, le champ est prérempli : vide = effacer,
            // sauf si la valeur stockée est illisible (le champ est alors vide malgré elle).
            if ($value === '') {
                $stored = $this->storedValue($postId, $field);

                if (empty($field['revealable']) || $this->isStoredUnreadable($stored)) {
                    $value = $stored;
                }
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
     * Vrai si la valeur brute est chiffrée mais ne peut pas être déchiffrée (clé absente ou autre clé).
     */
    private function isStoredUnreadable(string $stored): bool
    {
        if (!EncryptedValueCodec::isEncrypted($stored)) {
            return false;
        }

        $codec = EncryptedValueCodec::make();

        return $codec === null || $codec->decrypt($stored) === null;
    }

    /**
     * @param bool|string          $valid
     * @param mixed                $value
     * @param array<string, mixed> $field
     * @param string               $input
     */
    public function validate_value($valid, $value, $field, $input): bool|string
    {
        if ($valid !== true) {
            return $valid;
        }

        $submitted = is_array($value) ? (string) ($value['value'] ?? '') : (string) $value;

        if ($submitted !== '' && EncryptedValueCodec::make() === null) {
            return __('Clé de chiffrement (APP_KEY) absente : impossible d’enregistrer cette valeur.', 'horizon-tools');
        }

        return $valid;
    }

    /**
     * Dans un bloc ACF, la valeur est stockée dans les attributs du bloc (post_content)
     * sans passer par update_value : elle serait enregistrée en clair. On l'empêche.
     *
     * @param array<string, mixed> $field
     *
     * @return array<string, mixed>
     */
    public function prepare_field($field): array
    {
        if ($this->isRenderedInBlock($field)) {
            $field['instructions'] = __(
                'Champ chiffré indisponible dans un bloc : à placer dans une page d’options, un type de contenu, une taxonomie ou un profil.',
                'horizon-tools'
            );
            $field['disabled'] = 1;
        }

        return $field;
    }

    /**
     * Vrai si le champ est rendu dans un bloc ACF.
     *
     * ACF 6.8.10, pro/blocks.php (l. 1012, 1146, 1672) : le formulaire d’un bloc passe par
     * acf_prefix_fields($fields, "acf-{$block['id']}") avant acf_render_fields, et l’id de
     * bloc est toujours préfixé « block_ » (acf_ensure_block_id_prefix, l. 840).
     *
     * @param array<string, mixed> $field
     */
    private function isRenderedInBlock(array $field): bool
    {
        return str_starts_with((string) ($field['prefix'] ?? ''), 'acf-block_');
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
