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

/** Attribut « data » du premier bloc d’un contenu slashé. */
$dataOf = static function (string $saved): array {
    $blocks = parse_blocks(wp_unslash($saved));

    return (array) ($blocks[0]['attrs']['data'] ?? []);
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

acf_reset_meta(HZ_BLOCK_POST_ID);
foreach (['field_hz_blk_sub', 'field_hz_blk_grp', 'field_hz_blk'] as $key) {
    acf_remove_local_field($key);
}
acf_remove_local_field_group('group_hz_blk');
acf_remove_block_type(HZ_BLOCK);
unregister_block_type(HZ_BLOCK);
hz_done();
