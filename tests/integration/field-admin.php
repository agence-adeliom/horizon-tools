<?php

// Pas de declare(strict_types=1) : WP-CLI eval-file le refuse (« must be the very first statement »).

use Adeliom\HorizonTools\Services\Encryption\EncryptedValueCodec;

require __DIR__ . '/bootstrap.php';

$base = [
    'key' => 'field_hz_enc_admin',
    'name' => 'hz_enc_admin',
    'label' => 'Clé API',
    'type' => 'encrypted_text',
    'revealable' => 0,
];
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

// Champ sans réglage « revealable » (défini en code sans le wrapper) : affichable, prérempli.
$bare = ['key' => 'field_hz_enc_admin_bare', 'name' => 'hz_enc_admin', 'label' => 'Sans réglage', 'type' => 'encrypted_text'];
$bare['value'] = 'sk_secret_xyz';
$bare['placeholder'] = '';
$bare['name'] = 'acf[field_hz_enc_admin_bare]';
ob_start();
acf_get_field_type('encrypted_text')->render_field($bare);
$html = (string) ob_get_clean();
hz_check(str_contains($html, 'value="sk_secret_xyz"'), 'sans réglage : valeur préremplie (affichable par défaut)');

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

// I1 : valeur chiffrée illisible (autre clé) et soumission vide : on conserve, quel que soit le mode.
update_option('options_hz_enc_admin_rev', $foreign->encrypt('secret-perdu-rev'));
$foreignRaw = get_option('options_hz_enc_admin_rev');
acf_flush_value_cache('options', 'hz_enc_admin_rev');
get_field('hz_enc_admin_rev', 'options');
hz_check(
    str_contains($render('field_hz_enc_admin_rev'), 'name="acf[field_hz_enc_admin_rev][clear]"'),
    'affichable illisible : case « Effacer » présente'
);
acf_update_value(wp_slash(['value' => '']), 'options', acf_get_field('field_hz_enc_admin_rev'));
hz_check(get_option('options_hz_enc_admin_rev') === $foreignRaw, 'affichable illisible soumis vide : valeur brute conservée');
acf_update_value(wp_slash(['value' => '', 'clear' => '1']), 'options', acf_get_field('field_hz_enc_admin_rev'));
hz_check(get_option('options_hz_enc_admin_rev') === '', 'affichable illisible + « Effacer » : valeur supprimée');

// I1 sans clé : soumission vide = valeur brute inchangée.
$keyless = $foreign->encrypt('secret-sans-cle');
update_option('options_hz_enc_admin_rev', $keyless);
$savedKey = config('app.key');
config(['app.key' => '']);
app()->forgetInstance('encrypter');
acf_update_value(wp_slash(['value' => '']), 'options', acf_get_field('field_hz_enc_admin_rev'));
hz_check(get_option('options_hz_enc_admin_rev') === $keyless, 'sans clé, soumission vide : valeur brute inchangée');
config(['app.key' => $savedKey]);
app()->forgetInstance('encrypter');
delete_option('options_hz_enc_admin_rev');

// C1 : barres obliques de WordPress (acf_save_post reçoit $_POST échappé).
$tricky = 'a"b\\c\'d';
foreach (['field_hz_enc_admin' => 'hz_enc_admin', 'field_hz_enc_admin_rev' => 'hz_enc_admin_rev'] as $key => $name) {
    $mode = $key === 'field_hz_enc_admin' ? 'non affichable' : 'affichable';
    acf_update_value(wp_slash(['value' => $tricky]), 'options', acf_get_field($key));
    acf_flush_value_cache('options', $name);
    hz_check(get_field($name, 'options') === $tricky, 'C1 ' . $mode . ' : guillemets et barre oblique inverse intacts');
    acf_update_value(wp_slash(['value' => get_field($name, 'options')]), 'options', acf_get_field($key));
    acf_flush_value_cache('options', $name);
    hz_check(get_field($name, 'options') === $tricky, 'C1 ' . $mode . ' : second enregistrement sans barre ajoutée');
}

$type = acf_get_field_type('encrypted_text');

// Blocs ACF : stockage dans post_content, chiffré par EncryptedBlockData (voir block.php).
// Mécanisme ACF 6.8.10 : pro/blocks.php (l. 1012, 1146, 1672) appelle acf_prefix_fields($fields, "acf-{$block['id']}")
// avant acf_render_fields ; l’id de bloc porte toujours le préfixe « block_ » (acf_ensure_block_id_prefix, l. 840).
$blockField = acf_get_field('field_hz_enc_admin');
$blockField['prefix'] = 'acf-block_abc123';
$prepared = $type->prepare_field($blockField);
hz_check(
    is_array($prepared) && empty($prepared['disabled']) && !empty($prepared['revealable']),
    'dans un bloc : champ affichable et non désactivé'
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
