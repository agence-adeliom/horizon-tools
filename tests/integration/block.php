<?php

// Pas de declare(strict_types=1) : WP-CLI eval-file le refuse (« must be the very first statement »).

use Adeliom\HorizonTools\ACF\EncryptedBlockData;
use Adeliom\HorizonTools\Services\Encryption\EncryptedValueCodec;

require __DIR__ . '/bootstrap.php';

/*
 * Champ chiffré dans un bloc ACF classique : les valeurs vivent dans les attributs du bloc
 * (post_content). Enregistrement simulé avec acf_parse_save_blocks(), branché par ACF sur
 * content_save_pre (ACF 6.8.10, pro/blocks.php l. 1767).
 */

const HZ_BLOCK = 'acf/hz-encrypted-check';
const HZ_BLOCK_POST_ID = 'block_test';

acf_register_block_type([
    'name' => 'hz-encrypted-check',
    'title' => 'Contrôle champ chiffré',
    'mode' => 'edit',
    'render_callback' => static function (): void {},
]);

$location = [[['param' => 'block', 'operator' => '==', 'value' => HZ_BLOCK]]];
acf_add_local_field_group([
    'key' => 'group_hz_blk',
    'title' => 'Contrôle bloc chiffré',
    'fields' => [
        ['key' => 'field_hz_blk', 'name' => 'hz_blk', 'label' => 'Clé API', 'type' => 'encrypted_text', 'revealable' => 0],
        [
            'key' => 'field_hz_blk_grp',
            'name' => 'hz_blk_grp',
            'label' => 'Groupe',
            'type' => 'group',
            'sub_fields' => [['key' => 'field_hz_blk_sub', 'name' => 'sub', 'label' => 'Sous-clé', 'type' => 'encrypted_text']],
        ],
    ],
    'location' => $location,
]);

/**
 * Enregistre un bloc comme WordPress (contenu slashé) et renvoie le contenu slashé obtenu.
 *
 * @param array<string, mixed> $data
 */
$save = static function (array $data): string {
    $attrs = wp_json_encode(['name' => HZ_BLOCK, 'data' => $data, 'mode' => 'edit'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    return acf_parse_save_blocks(wp_slash('<!-- wp:' . HZ_BLOCK . ' ' . $attrs . ' /-->'));
};

/** Attribut « data » du premier bloc de contrôle d’un contenu slashé. */
$dataOf = static function (string $saved): array {
    foreach (parse_blocks(wp_unslash($saved)) as $block) {
        if ($block['blockName'] === HZ_BLOCK) {
            return (array) ($block['attrs']['data'] ?? []);
        }
    }

    return [];
};

/** Lecture comme au rendu du bloc : local meta + get_field(). */
$read = static function (string $saved, string $name) use ($dataOf): mixed {
    acf_setup_meta($dataOf($saved), HZ_BLOCK_POST_ID, true);
    // Cache des valeurs vidé en entier : un groupe met aussi en cache ses sous-champs (« groupe_sous »).
    acf_get_store('values')->reset();
    $value = get_field($name, HZ_BLOCK_POST_ID);
    acf_reset_meta(HZ_BLOCK_POST_ID);

    return $value;
};

// a) Données venues du code ou du formulaire (format « requête » : clé de champ => valeur).
$saved = $save(['field_hz_blk' => 'sk_block_secret']);
hz_check(!str_contains($saved, 'sk_block_secret'), 'a) le clair n’est plus dans post_content');
hz_check(str_contains($saved, EncryptedValueCodec::PREFIX), 'a) post_content contient une valeur chiffrée');

// b) Lecture dans le rendu du bloc.
hz_check($read($saved, 'hz_blk') === 'sk_block_secret', 'b) get_field() dans le bloc renvoie le clair');

// c) Réenregistrement (format « meta », déjà chiffré) : pas de double chiffrement.
$encrypted = $dataOf($saved)['hz_blk'] ?? null;
$resaved = acf_parse_save_blocks($saved);
hz_check(is_string($encrypted) && ($dataOf($resaved)['hz_blk'] ?? null) === $encrypted, 'c) réenregistrement : valeur chiffrée identique');

// Format « meta » en clair (bloc inséré par le code ou valeur antérieure) : chiffré par acf/pre_save_block.
$saved = $save(['hz_blk' => 'sk_meta_plain', '_hz_blk' => 'field_hz_blk']);
hz_check(!str_contains($saved, 'sk_meta_plain'), 'format meta en clair : le clair n’est plus dans post_content');
hz_check($read($saved, 'hz_blk') === 'sk_meta_plain', 'format meta en clair : relu en clair');

// d) Formulaire du bloc : acf.serialize() produit { field_key: { value, clear } }.
$saved = $save(['field_hz_blk' => ['value' => 'x']]);
hz_check(EncryptedValueCodec::isEncrypted($dataOf($saved)['hz_blk'] ?? null), 'd) formulaire [value] : chiffré');
hz_check($read($saved, 'hz_blk') === 'x', 'd) formulaire [value] : relu en clair');
$saved = $save(['field_hz_blk' => ['value' => '', 'clear' => '1']]);
hz_check(($dataOf($saved)['hz_blk'] ?? null) === '', 'd) formulaire [clear] : valeur vide');
$saved = $save(['field_hz_blk' => ['value' => '']]);
hz_check(($dataOf($saved)['hz_blk'] ?? null) === '', 'd) formulaire vide : valeur effacée (affichable dans un bloc)');

$filtered = EncryptedBlockData::encryptAttributes(['data' => ['hz_blk' => ['value' => 'y'], '_hz_blk' => 'field_hz_blk']]);
hz_check(EncryptedValueCodec::isEncrypted($filtered['data']['hz_blk'] ?? null), 'd) filtre, tableau [value] : chiffré');
$filtered = EncryptedBlockData::encryptAttributes(['data' => ['hz_blk' => ['value' => 'y', 'clear' => '1'], '_hz_blk' => 'field_hz_blk']]);
hz_check(($filtered['data']['hz_blk'] ?? null) === '', 'd) filtre, tableau [clear] : valeur vide');

// Valeurs non magic-quotées dans un bloc : guillemets et barre oblique inverse intacts.
$tricky = 'a"b\\c\'d';
$saved = $save(['field_hz_blk' => ['value' => $tricky]]);
hz_check($read($saved, 'hz_blk') === $tricky, 'guillemets et barre oblique inverse intacts');

// e) Sous-champ d’un groupe, en format requête puis en format meta.
$saved = $save(['field_hz_blk_grp' => ['field_hz_blk_sub' => 'sk_sub_secret']]);
hz_check(!str_contains($saved, 'sk_sub_secret'), 'e) sous-champ (requête) : le clair n’est plus dans post_content');
hz_check(EncryptedValueCodec::isEncrypted($dataOf($saved)['hz_blk_grp_sub'] ?? null), 'e) sous-champ (requête) : chiffré');
hz_check(($read($saved, 'hz_blk_grp')['sub'] ?? null) === 'sk_sub_secret', 'e) sous-champ (requête) : relu en clair');
$saved = $save([
    'hz_blk_grp_sub' => 'sk_sub_meta',
    '_hz_blk_grp_sub' => 'field_hz_blk_sub',
    'hz_blk_grp' => '',
    '_hz_blk_grp' => 'field_hz_blk_grp',
]);
hz_check(!str_contains($saved, 'sk_sub_meta'), 'e) sous-champ (meta) : le clair n’est plus dans post_content');
hz_check(($read($saved, 'hz_blk_grp')['sub'] ?? null) === 'sk_sub_meta', 'e) sous-champ (meta) : relu en clair');

// Bloc sans « data » (bloc vide, ou use_post_meta : data retiré avant le filtre).
$attrs = ['name' => HZ_BLOCK, 'mode' => 'edit'];
hz_check(EncryptedBlockData::encryptAttributes($attrs) === $attrs, 'attributs sans data : inchangés');

// f) Sans clé : aucune exception, aucun clair, valeur vide.
$savedKey = config('app.key');
config(['app.key' => '']);
app()->forgetInstance('encrypter');
$threw = false;
$results = [];
try {
    $results[] = $save(['field_hz_blk' => 'sk_nokey_request']);
    $results[] = $save(['field_hz_blk' => ['value' => 'sk_nokey_form']]);
    $results[] = $save(['hz_blk' => 'sk_nokey_meta', '_hz_blk' => 'field_hz_blk']);
} catch (\Throwable $e) {
    $threw = true;
}
config(['app.key' => $savedKey]);
app()->forgetInstance('encrypter');
hz_check(!$threw, 'f) sans clé : aucune exception');
hz_check(count($results) === 3, 'f) sans clé : les trois enregistrements aboutissent');
foreach ($results as $i => $result) {
    hz_check(!str_contains($result, 'sk_nokey'), sprintf('f) sans clé (%d) : aucun clair dans post_content', $i + 1));
    hz_check(($dataOf($result)['hz_blk'] ?? null) === '', sprintf('f) sans clé (%d) : valeur vide', $i + 1));
}

// g) Rendu dans un bloc : affichable, non désactivé, sans avertissement d’indisponibilité.
$field = acf_get_field('field_hz_blk');
$field['prefix'] = 'acf-block_abc123';
$prepared = acf_get_field_type('encrypted_text')->prepare_field($field);
hz_check(is_array($prepared) && empty($prepared['disabled']), 'g) dans un bloc : champ non désactivé');
hz_check(is_array($prepared) && !empty($prepared['revealable']), 'g) dans un bloc : champ affichable');
hz_check(
    is_array($prepared) && !str_contains((string) ($prepared['instructions'] ?? ''), 'indisponible'),
    'g) dans un bloc : pas d’avertissement'
);

// Essai réel : brouillon enregistré par wp_insert_post(), post_content relu en base.
global $wpdb;
$attrs = wp_json_encode(['name' => HZ_BLOCK, 'data' => ['field_hz_blk' => 'sk_insert_secret'], 'mode' => 'edit'], JSON_UNESCAPED_SLASHES);
$postId = wp_insert_post(
    wp_slash([
        'post_title' => 'Contrôle bloc chiffré',
        'post_status' => 'draft',
        'post_type' => 'post',
        'post_content' => '<!-- wp:' . HZ_BLOCK . ' ' . $attrs . ' /-->',
    ]),
    true
);
if (is_wp_error($postId)) {
    hz_check(false, 'brouillon : insertion (' . $postId->get_error_message() . ')');
} else {
    $stored = (string) $wpdb->get_var($wpdb->prepare("SELECT post_content FROM {$wpdb->posts} WHERE ID = %d", $postId));
    hz_check(!str_contains($stored, 'sk_insert_secret'), 'brouillon : le clair n’est pas en base');
    hz_check($read(wp_slash($stored), 'hz_blk') === 'sk_insert_secret', 'brouillon : valeur relue en clair');
    wp_delete_post($postId, true);
}

// Widgets « Bloc » : WP_Widget_Block::update() n’applique pas content_save_pre. Toutes les écritures
// passent par update_option('widget_block') : filtre appelé comme le fait update_option(), sans écrire.
$blockComment = static function (array $data): string {
    return '<!-- wp:' .
        HZ_BLOCK .
        ' ' .
        wp_json_encode(['name' => HZ_BLOCK, 'data' => $data, 'mode' => 'edit'], JSON_UNESCAPED_SLASHES) .
        ' /-->';
};
$instances = apply_filters(
    'pre_update_option_widget_block',
    [
        2 => ['content' => $blockComment(['field_hz_blk' => ['value' => 'sk_widget_form']])],
        3 => [
            'content' =>
                '<!-- wp:paragraph --><p>Texte</p><!-- /wp:paragraph -->' .
                $blockComment(['hz_blk' => 'sk_widget_meta', '_hz_blk' => 'field_hz_blk']),
        ],
        '_multiwidget' => 1,
    ],
    [],
    'widget_block'
);
$serialized = serialize($instances);
hz_check(!str_contains($serialized, 'sk_widget_'), 'widget : aucun clair dans l’option widget_block');
hz_check($read(wp_slash($instances[2]['content']), 'hz_blk') === 'sk_widget_form', 'widget (formulaire) : relu en clair');
hz_check($read(wp_slash($instances[3]['content']), 'hz_blk') === 'sk_widget_meta', 'widget (format meta) : relu en clair');
hz_check(
    str_contains($instances[3]['content'], '<p>Texte</p>') && ($instances['_multiwidget'] ?? null) === 1,
    'widget : reste du contenu intact'
);
hz_check(EncryptedBlockData::encryptWidgets(false) === false, 'widget : option absente inchangée');

// Valeur illisible (APP_KEY changée) dans le formulaire du bloc : la valeur chiffrée préremplit le champ.
// Formulaire rendu comme ACF (pro/blocks.php l. 1003-1015) : local meta, acf_prefix_fields, acf_render_fields.
$foreign = new EncryptedValueCodec(
    new Illuminate\Encryption\Encrypter(Illuminate\Encryption\Encrypter::generateKey('AES-256-CBC'), 'AES-256-CBC')
);
$foreignValue = $foreign->encrypt('secret-autre-cle');
$foreignSub = $foreign->encrypt('sous-secret-autre-cle');
$renderForm = static function (array $data): string {
    acf_setup_meta($data, HZ_BLOCK_POST_ID, true);
    // Lecture préalable (validation, aperçu) : met en cache une valeur vide.
    get_field('hz_blk', HZ_BLOCK_POST_ID);
    $fields = acf_get_block_fields(['name' => HZ_BLOCK]);
    acf_prefix_fields($fields, 'acf-' . HZ_BLOCK_POST_ID);
    ob_start();
    acf_render_fields($fields, HZ_BLOCK_POST_ID, 'div', 'field');

    return (string) ob_get_clean();
};
$html = $renderForm([
    'hz_blk' => $foreignValue,
    '_hz_blk' => 'field_hz_blk',
    'hz_blk_grp' => '',
    '_hz_blk_grp' => 'field_hz_blk_grp',
    'hz_blk_grp_sub' => $foreignSub,
    '_hz_blk_grp_sub' => 'field_hz_blk_sub',
]);
hz_check(str_contains($html, 'value="' . esc_attr($foreignValue) . '"'), 'illisible : la valeur chiffrée préremplit le champ du bloc');
hz_check(str_contains($html, 'value="' . esc_attr($foreignSub) . '"'), 'illisible : idem pour un sous-champ de groupe');
hz_check(str_contains($html, 'hz-encrypted-text__unreadable'), 'illisible : avertissement affiché');
hz_check(str_contains($html, 'name="acf-block_test[field_hz_blk][clear]"'), 'illisible : case « Effacer » proposée');
hz_check(!str_contains($html, 'secret-autre-cle'), 'illisible : aucun clair');
acf_get_store('values')->reset();
hz_check(get_field('hz_blk', HZ_BLOCK_POST_ID) === '', 'illisible : hors formulaire, get_field() renvoie toujours une chaîne vide');
acf_reset_meta(HZ_BLOCK_POST_ID);
$saved = $save(['field_hz_blk' => ['value' => $foreignValue], 'field_hz_blk_grp' => ['field_hz_blk_sub' => ['value' => $foreignSub]]]);
hz_check(($dataOf($saved)['hz_blk'] ?? null) === $foreignValue, 'illisible : réenregistrée telle quelle');
hz_check(($dataOf($saved)['hz_blk_grp_sub'] ?? null) === $foreignSub, 'illisible : sous-champ réenregistré tel quel');

// Sans clé : même conservation.
$saved = $save(['field_hz_blk' => 'sk_avant_perte']);
$ownValue = $dataOf($saved)['hz_blk'] ?? '';
config(['app.key' => '']);
app()->forgetInstance('encrypter');
$html = $renderForm(['hz_blk' => $ownValue, '_hz_blk' => 'field_hz_blk']);
acf_reset_meta(HZ_BLOCK_POST_ID);
$saved = $save(['field_hz_blk' => ['value' => $ownValue]]);
config(['app.key' => $savedKey]);
app()->forgetInstance('encrypter');
hz_check(str_contains($html, 'value="' . esc_attr($ownValue) . '"'), 'sans clé : la valeur chiffrée préremplit le champ du bloc');
hz_check(($dataOf($saved)['hz_blk'] ?? null) === $ownValue, 'sans clé : réenregistrée telle quelle');

// Bloc use_post_meta : valeurs enregistrées en post meta par acf_save_post au save_post
// (pro/blocks.php l. 1795-1801 et 2136-2146), via un vrai wp_insert_post().
const HZ_META_BLOCK = 'acf/hz-encrypted-meta';
acf_register_block_type([
    'name' => 'hz-encrypted-meta',
    'title' => 'Contrôle champ chiffré (post meta)',
    'mode' => 'edit',
    'use_post_meta' => true,
    'render_callback' => static function (): void {},
]);
acf_add_local_field_group([
    'key' => 'group_hz_blkm',
    'title' => 'Contrôle bloc chiffré (post meta)',
    'fields' => [['key' => 'field_hz_blkm', 'name' => 'hz_blkm', 'label' => 'Clé API', 'type' => 'encrypted_text']],
    'location' => [[['param' => 'block', 'operator' => '==', 'value' => HZ_META_BLOCK]]],
]);
$insertMetaBlock = static function (array $data): int|WP_Error {
    $attrs = wp_json_encode(['name' => HZ_META_BLOCK, 'data' => $data, 'mode' => 'edit'], JSON_UNESCAPED_SLASHES);

    return wp_insert_post(
        wp_slash([
            'post_title' => 'Contrôle bloc chiffré (post meta)',
            'post_status' => 'draft',
            'post_type' => 'post',
            'post_content' => '<!-- wp:' . HZ_META_BLOCK . ' ' . $attrs . ' /-->',
        ]),
        true
    );
};
$metaPosts = [];
$postId = $insertMetaBlock(['field_hz_blkm' => ['value' => $tricky]]);
if (is_wp_error($postId)) {
    hz_check(false, 'use_post_meta : insertion (' . $postId->get_error_message() . ')');
} else {
    $metaPosts[] = $postId;
    $raw = get_post_meta($postId, 'hz_blkm', true);
    hz_check(EncryptedValueCodec::isEncrypted($raw), 'use_post_meta : post meta chiffrée');
    hz_check(get_field('hz_blkm', $postId) === $tricky, 'use_post_meta : guillemets et barre oblique inverse intacts');
}
$postId = $insertMetaBlock(['hz_blkm' => 'sk_meta_format', '_hz_blkm' => 'field_hz_blkm']);
if (!is_wp_error($postId)) {
    $metaPosts[] = $postId;
    hz_check(EncryptedValueCodec::isEncrypted(get_post_meta($postId, 'hz_blkm', true)), 'use_post_meta (format meta) : post meta chiffrée');
}
config(['app.key' => '']);
app()->forgetInstance('encrypter');
$threw = false;
try {
    $postId = $insertMetaBlock(['field_hz_blkm' => ['value' => 'sk_meta_nokey']]);
} catch (\Throwable $e) {
    $threw = true;
}
config(['app.key' => $savedKey]);
app()->forgetInstance('encrypter');
hz_check(!$threw && !is_wp_error($postId), 'use_post_meta sans clé : aucune exception');
if (!$threw && !is_wp_error($postId)) {
    $metaPosts[] = $postId;
    hz_check(get_post_meta($postId, 'hz_blkm', true) === '', 'use_post_meta sans clé : valeur vide');
    $allMeta = serialize(get_post_meta($postId)) . get_post_field('post_content', $postId);
    hz_check(!str_contains($allMeta, 'sk_meta_nokey'), 'use_post_meta sans clé : aucun clair');
}
foreach ($metaPosts as $metaPost) {
    wp_delete_post($metaPost, true);
}

acf_reset_meta(HZ_BLOCK_POST_ID);
foreach (['field_hz_blk_sub', 'field_hz_blk_grp', 'field_hz_blk', 'field_hz_blkm'] as $key) {
    acf_remove_local_field($key);
}
acf_remove_local_field_group('group_hz_blk');
acf_remove_local_field_group('group_hz_blkm');
foreach ([HZ_BLOCK, HZ_META_BLOCK] as $blockName) {
    acf_remove_block_type($blockName);
    unregister_block_type($blockName);
}
hz_done();
