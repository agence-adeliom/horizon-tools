<?php

// Pas de declare(strict_types=1) : WP-CLI eval-file le refuse (« must be the very first statement »).

use Adeliom\HorizonTools\Services\Encryption\EncryptedValueCodec;

require __DIR__ . '/bootstrap.php';

$base = ['key' => 'field_hz_enc_admin', 'name' => 'hz_enc_admin', 'label' => 'Clé API', 'type' => 'encrypted_text'];
acf_add_local_field_group([
    'key' => 'group_hz_enc_admin',
    'title' => 'Contrôle admin',
    'fields' => [
        $base,
        [
            'key' => 'field_hz_enc_admin_rev',
            'name' => 'hz_enc_admin_rev',
            'label' => 'Clé visible',
            'type' => 'encrypted_text',
            'revealable' => 1,
        ],
    ],
    'location' => [[['param' => 'options_page', 'operator' => '==', 'value' => 'hz-enc-admin']]],
]);

update_field('field_hz_enc_admin', 'sk_secret_xyz', 'options');
update_field('field_hz_enc_admin_rev', 'sk_visible_xyz', 'options');
$encryptedBefore = get_option('options_hz_enc_admin');

$render = static function (string $key): string {
    $field = acf_get_field($key);
    $field['value'] = acf_get_value('options', $field);
    $field['name'] = 'acf[' . $key . ']';
    ob_start();
    acf_get_field_type('encrypted_text')->render_field($field);

    return (string) ob_get_clean();
};

$html = $render('field_hz_enc_admin');
hz_check(!str_contains($html, 'sk_secret_xyz'), 'non affichable : le clair n’est pas dans le HTML');
hz_check(str_contains($html, 'name="acf[field_hz_enc_admin][clear]"'), 'non affichable : case « Effacer » présente');

$html = $render('field_hz_enc_admin_rev');
hz_check(str_contains($html, 'value="sk_visible_xyz"'), 'affichable : valeur préremplie');
hz_check(str_contains($html, 'hz-encrypted-text__toggle'), 'affichable : bouton « Afficher » présent');

// Soumission du formulaire : vide = conserver (non affichable)
acf_update_value(['value' => ''], 'options', acf_get_field('field_hz_enc_admin'));
hz_check(get_option('options_hz_enc_admin') === $encryptedBefore, 'non affichable soumis vide : valeur conservée à l’identique');

acf_update_value(['value' => 'sk_nouvelle'], 'options', acf_get_field('field_hz_enc_admin'));
acf_flush_value_cache('options', 'hz_enc_admin');
hz_check(get_field('hz_enc_admin', 'options') === 'sk_nouvelle', 'nouvelle saisie : remplace la valeur');

acf_update_value(['value' => '', 'clear' => '1'], 'options', acf_get_field('field_hz_enc_admin'));
hz_check(get_option('options_hz_enc_admin') === '', 'case « Effacer » : valeur supprimée');

acf_update_value(['value' => ''], 'options', acf_get_field('field_hz_enc_admin_rev'));
hz_check(get_option('options_hz_enc_admin_rev') === '', 'affichable soumis vide : valeur effacée');

// Valeur illisible : avertissement visible, pas un champ silencieusement vide
$foreign = new EncryptedValueCodec(
    new Illuminate\Encryption\Encrypter(Illuminate\Encryption\Encrypter::generateKey('AES-256-CBC'), 'AES-256-CBC')
);
update_option('options_hz_enc_admin', $foreign->encrypt('secret-perdu'));
acf_flush_value_cache('options', 'hz_enc_admin');
hz_check(
    str_contains($render('field_hz_enc_admin'), 'hz-encrypted-text__unreadable'),
    'valeur illisible : avertissement affiché dans le champ'
);

$type = acf_get_field_type('encrypted_text');

// Blocs ACF : stockage dans post_content, hors update_value.
// Mécanisme ACF 6.8.10 : pro/blocks.php (l. 1012, 1146, 1672) appelle acf_prefix_fields($fields, "acf-{$block['id']}")
// avant acf_render_fields ; l’id de bloc porte toujours le préfixe « block_ » (acf_ensure_block_id_prefix, l. 840).
$blockField = acf_get_field('field_hz_enc_admin');
$blockField['prefix'] = 'acf-block_abc123';
$prepared = $type->prepare_field($blockField);
hz_check(
    is_array($prepared) && str_contains((string) ($prepared['instructions'] ?? ''), 'bloc') && !empty($prepared['disabled']),
    'dans un bloc : avertissement affiché et champ désactivé'
);
$prepared = $type->prepare_field(acf_get_field('field_hz_enc_admin'));
hz_check(empty($prepared['disabled']), 'hors bloc : champ non désactivé');

// Validation sans clé
$valid = $type->validate_value(true, ['value' => 'x'], acf_get_field('field_hz_enc_admin'), 'acf[field_hz_enc_admin]');
hz_check($valid === true, 'avec clé : saisie valide');
config(['app.key' => '']);
app()->forgetInstance('encrypter');
$valid = $type->validate_value(true, ['value' => 'x'], acf_get_field('field_hz_enc_admin'), 'acf[field_hz_enc_admin]');
hz_check(is_string($valid) && str_contains($valid, 'APP_KEY'), 'sans clé : saisie refusée avec un message');
$valid = $type->validate_value(true, ['value' => ''], acf_get_field('field_hz_enc_admin'), 'acf[field_hz_enc_admin]');
hz_check($valid === true, 'sans clé : soumission vide acceptée (rien à chiffrer)');

foreach (['hz_enc_admin', 'hz_enc_admin_rev'] as $name) {
    delete_option('options_' . $name);
    delete_option('_options_' . $name);
}
hz_done();
