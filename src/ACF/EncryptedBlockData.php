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
 * - Bloc `use_post_meta` : `data` est retiré avant le filtre et mis en attente dans le
 *   magasin « block-meta-values » (l. 1795-1801), puis enregistré par acf_save_post au
 *   save_post (l. 2136-2146). Ce filtre chiffre les valeurs en attente : acf_save_post ne
 *   reçoit plus que des valeurs chiffrées (ou vides sans clé), que update_value conserve
 *   telles quelles, sans wp_unslash ni exception.
 * - Widgets « Bloc » : WP_Widget_Block::update() n’applique pas content_save_pre ;
 *   encryptWidgets() est branché sur pre_update_option_widget_block.
 */
final class EncryptedBlockData
{
    /** Expression d’un commentaire de bloc, reprise d’acf_parse_save_blocks (pro/blocks.php l. 1759). */
    private const BLOCK_COMMENT = '/<!--\s+wp:(?P<name>[\S]+)\s+(?P<attrs>{[\S\s]+?})\s+(?P<void>\/)?-->/';

    /**
     * Identifiants des blocs dont le formulaire est en cours de rendu (pile : acf_render_fields
     * peut être imbriqué). Une entrée null correspond à un rendu hors formulaire de bloc.
     *
     * @var list<string|null>
     */
    private static array $renderingForms = [];

    /**
     * Filtre acf/pre_save_block : chiffre les valeurs des champs chiffrés du bloc.
     *
     * Ne lève jamais d’exception (elle bloquerait l’enregistrement de toute la page) et ne
     * laisse jamais de clair.
     */
    public static function encryptAttributes(mixed $attrs): mixed
    {
        if (!is_array($attrs)) {
            return $attrs;
        }

        if (isset($attrs['data']) && is_array($attrs['data'])) {
            $attrs['data'] = self::encryptData($attrs['data']);

            return $attrs;
        }

        // Bloc use_post_meta : valeurs en attente d’acf_save_post (pro/blocks.php l. 1795-1801).
        $blockId = $attrs['id'] ?? null;

        if (is_string($blockId) && function_exists('acf_block_uses_post_meta') && acf_block_uses_post_meta($attrs)) {
            $store = acf_get_store('block-meta-values');

            if ($store->has($blockId) && is_array($store->get($blockId))) {
                $store->set($blockId, self::encryptData($store->get($blockId)));
            }
        }

        return $attrs;
    }

    /**
     * Chiffre les champs chiffrés d’un tableau de valeurs de bloc, au format meta
     * (`nom => valeur`, `_nom => clé`) comme au format requête (`clé => valeur`, imbriqué
     * pour les groupes et répéteurs).
     *
     * @param array<mixed> $data
     *
     * @return array<mixed>
     */
    public static function encryptData(array $data): array
    {
        // Format meta : les sous-champs ont leur propre entrée `_groupe_sous => clé du sous-champ`.
        foreach ($data as $reference => $key) {
            if (!is_string($reference) || !str_starts_with($reference, '_') || !is_string($key) || !acf_is_field_key($key)) {
                continue;
            }

            $name = substr($reference, 1);

            if (array_key_exists($name, $data) && self::isEncryptedField($key)) {
                $data[$name] = self::encryptValue($data[$name], $name);
            }
        }

        return self::encryptRequestData($data);
    }

    /**
     * Format requête : `clé de champ => valeur`, imbriqué (groupe : clés des sous-champs ;
     * répéteur : lignes `row-0` => clés des sous-champs).
     *
     * @param array<mixed> $data
     *
     * @return array<mixed>
     */
    private static function encryptRequestData(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_string($key) && acf_is_field_key($key) && self::isEncryptedField($key)) {
                $field = acf_get_field($key);
                $data[$key] = self::encryptValue($value, (string) ($field['name'] ?? $key));
            } elseif (is_array($value)) {
                $data[$key] = self::encryptRequestData($value);
            }
        }

        return $data;
    }

    private static function isEncryptedField(string $key): bool
    {
        $field = acf_get_field($key);

        return is_array($field) && ($field['type'] ?? '') === AcfFieldEncryptedText::TYPE;
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

    /**
     * Filtre pre_update_option_widget_block : chiffre les blocs ACF des widgets « Bloc ».
     *
     * WP_Widget_Block::update() (wp-includes/widgets/class-wp-widget-block.php l. 180-190)
     * copie `content` sans content_save_pre. Toutes les écritures (écran des widgets, API REST,
     * personnaliseur, qui contourne widget_update_callback avec raw_instance, class-wp-customize-
     * widgets.php l. 1466-1477) passent par update_option('widget_block'), avec des valeurs
     * sans barres obliques.
     *
     * acf_parse_save_blocks n’est pas réutilisé : pour un bloc use_post_meta, il retirerait
     * `data` pour un enregistrement au save_post qui n’a jamais lieu pour un widget.
     */
    public static function encryptWidgets(mixed $instances): mixed
    {
        if (!is_array($instances) || !function_exists('acf_has_block_type')) {
            return $instances;
        }

        foreach ($instances as $number => $instance) {
            if (
                is_array($instance) &&
                isset($instance['content']) &&
                is_string($instance['content']) &&
                str_contains($instance['content'], '<!-- wp:')
            ) {
                $instances[$number]['content'] = self::encryptBlockComments($instance['content']);
            }
        }

        return $instances;
    }

    /**
     * Chiffre les champs chiffrés des blocs ACF d’un contenu sans barres obliques, comme
     * acf_parse_save_blocks_callback (pro/blocks.php l. 1777) hors use_post_meta.
     */
    private static function encryptBlockComments(string $content): string
    {
        $encrypted = preg_replace_callback(
            self::BLOCK_COMMENT,
            static function (array $matches): string {
                $attrs = json_decode($matches['attrs'], true);

                if (!is_array($attrs) || empty($attrs['data']) || !is_array($attrs['data']) || !acf_has_block_type($matches['name'])) {
                    return $matches[0];
                }

                // Format requête converti au format meta (update_value chiffre au passage), puis
                // format meta chiffré.
                $blockId = acf_ensure_block_id_prefix(acf_get_block_id($attrs));
                $attrs['data'] = self::encryptData(acf_setup_meta($attrs['data'], $blockId));
                acf_reset_meta($blockId);

                return sprintf('<!-- wp:%s %s %s-->', $matches['name'], acf_serialize_block_attributes($attrs), $matches['void'] ?? '');
            },
            $content
        );

        return is_string($encrypted) ? $encrypted : $content;
    }

    /**
     * Filtre acf/pre_render_fields : début du rendu de champs. Si c’est le formulaire d’un bloc
     * (champs préfixés « acf-block_… », pro/blocks.php l. 1012, 1146, 1672), load_value renvoie
     * la valeur chiffrée illisible telle quelle (voir AcfFieldEncryptedText::load_value).
     *
     * Le cache des valeurs du bloc est vidé au début et à la fin : une valeur lue avant (vide)
     * ne doit pas masquer la valeur chiffrée dans le formulaire, ni l’inverse dans l’aperçu.
     *
     * @param mixed $fields Champs à rendre (tableau).
     * @param mixed $postId Identifiant de l’objet dont les valeurs sont lues.
     */
    public static function beginFieldsRender(mixed $fields, mixed $postId = 0): mixed
    {
        $blockId = null;

        if (is_string($postId) && is_array($fields) && self::isBlockPostId($postId)) {
            foreach ($fields as $field) {
                if (is_array($field) && str_starts_with((string) ($field['prefix'] ?? ''), 'acf-block_')) {
                    $blockId = $postId;
                    break;
                }
            }
        }

        self::$renderingForms[] = $blockId;

        if ($blockId !== null) {
            self::flushValues($blockId);
        }

        return $fields;
    }

    /**
     * Action acf/render_fields : fin du rendu (toujours appelée après acf/pre_render_fields,
     * includes/acf-field-functions.php l. 610-639).
     */
    public static function endFieldsRender(): void
    {
        $blockId = array_pop(self::$renderingForms);

        if ($blockId !== null) {
            self::flushValues($blockId);
        }
    }

    /**
     * Vrai pendant le rendu du formulaire du bloc `$postId`.
     *
     * @param int|string $postId
     */
    public static function isRenderingForm(mixed $postId): bool
    {
        return is_string($postId) && in_array($postId, self::$renderingForms, true);
    }

    /**
     * Vrai pour l’identifiant d’un bloc ACF stocké dans post_content. Le préfixe « block_ » est
     * réservé aux blocs par ACF (acf_ensure_block_id_prefix, pro/blocks.php l. 1868 ;
     * acf_decode_post_id, includes/acf-wp-functions.php l. 192).
     *
     * @param int|string $postId
     */
    public static function isBlockPostId(mixed $postId): bool
    {
        return is_string($postId) && acf_decode_post_id($postId)['type'] === 'block';
    }

    private static function flushValues(string $blockId): void
    {
        $store = acf_get_store('values');
        $values = $store->get();

        foreach (is_array($values) ? array_keys($values) : [] as $key) {
            if (str_starts_with((string) $key, $blockId . ':')) {
                $store->remove((string) $key);
            }
        }
    }
}
