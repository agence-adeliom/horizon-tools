<?php

// Pas de declare(strict_types=1) : WP-CLI eval-file le refuse (« must be the very first statement »).

use Adeliom\HorizonTools\Services\Encryption\EncryptedValueCodec;

require __DIR__ . '/bootstrap.php';

acf_add_local_field_group([
    'key' => 'group_hz_encrypted_check',
    'title' => 'Contrôle champ chiffré',
    'fields' => [['key' => 'field_hz_encrypted_check', 'name' => 'hz_encrypted_check', 'label' => 'Clé API', 'type' => 'encrypted_text']],
    'location' => [[['param' => 'options_page', 'operator' => '==', 'value' => 'hz-encrypted-check']]],
]);

hz_check(acf_get_field_type('encrypted_text') !== null, 'type de champ « encrypted_text » enregistré');

// Écriture par le code
update_field('field_hz_encrypted_check', 'sk_live_abc', 'options');
$raw = get_option('options_hz_encrypted_check');
hz_check(EncryptedValueCodec::isEncrypted($raw), 'valeur stockée chiffrée en base');
hz_check(get_field('hz_encrypted_check', 'options') === 'sk_live_abc', 'get_field() renvoie le clair');
hz_check(get_field('hz_encrypted_check', 'options', false) === 'sk_live_abc', 'get_field(…, false) renvoie aussi le clair');

// Valeur héritée en clair
update_option('options_hz_encrypted_check', 'ancienne-valeur-claire');
acf_flush_value_cache('options', 'hz_encrypted_check');
hz_check(get_field('hz_encrypted_check', 'options') === 'ancienne-valeur-claire', 'valeur héritée en clair lisible');

// Valeur chiffrée avec une autre clé
$foreign = new EncryptedValueCodec(
    new Illuminate\Encryption\Encrypter(Illuminate\Encryption\Encrypter::generateKey('AES-256-CBC'), 'AES-256-CBC')
);
update_option('options_hz_encrypted_check', $foreign->encrypt('secret-prod'));
acf_flush_value_cache('options', 'hz_encrypted_check');
hz_check(get_field('hz_encrypted_check', 'options') === '', 'valeur d’une autre clé : chaîne vide, pas d’erreur');

// Effacement par le code
update_field('field_hz_encrypted_check', '', 'options');
hz_check(get_option('options_hz_encrypted_check') === '', 'update_field(…, \'\') efface la valeur');

// Sans clé : lecture
update_field('field_hz_encrypted_check', 'avant-perte-cle', 'options');
$storedBefore = get_option('options_hz_encrypted_check');
config(['app.key' => '']);
app()->forgetInstance('encrypter');
acf_flush_value_cache('options', 'hz_encrypted_check');
hz_check(get_field('hz_encrypted_check', 'options') === '', 'sans APP_KEY : valeur chiffrée lue comme chaîne vide');

// Sans clé : écriture refusée, aucun clair en base
$threw = false;
try {
    update_field('field_hz_encrypted_check', 'nouveau', 'options');
} catch (\RuntimeException $e) {
    $threw = true;
}
hz_check($threw, 'sans APP_KEY : écriture refusée (RuntimeException)');
hz_check(get_option('options_hz_encrypted_check') === $storedBefore, 'sans APP_KEY : valeur en base inchangée, pas de clair');

delete_option('options_hz_encrypted_check');
delete_option('_options_hz_encrypted_check');
hz_done();
