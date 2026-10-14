<?php

// Pas de declare(strict_types=1) : WP-CLI eval-file le refuse (« must be the very first statement »).

use Adeliom\HorizonTools\Services\Encryption\EncryptedValueCodec;
use Adeliom\HorizonTools\Services\Encryption\EncryptedValueRepository;
use Adeliom\HorizonTools\Services\Encryption\EncryptionSnapshot;
use Illuminate\Encryption\Encrypter;

require __DIR__ . '/bootstrap.php';

$codec = EncryptedValueCodec::make();
$repo = new EncryptedValueRepository();
$prod = new EncryptedValueCodec(new Encrypter(Encrypter::generateKey('AES-256-CBC'), 'AES-256-CBC'));
$path = sys_get_temp_dir() . '/hz-snapshot-check.json';

// État local avant le pull : trois valeurs lisibles
update_option('options_hz_snap_a', $codec->encrypt('local-a'));
update_option('_options_hz_snap_a', 'field_hz_snap_a');
update_option('options_hz_snap_common', $codec->encrypt('local-common'));
update_option('options_hz_snap_new', $codec->encrypt('local-new'));
update_option('_options_hz_snap_new', 'field_hz_snap_new');

hz_check(EncryptionSnapshot::capture($repo, $codec, $path) >= 3, 'capture : au moins 3 valeurs sauvegardées');
hz_check(!str_contains((string) file_get_contents($path), 'local-a'), 'instantané : aucune valeur en clair');

// Simulation de l'import : valeur prod illisible, valeur prod lisible (clé commune), valeur absente en prod
update_option('options_hz_snap_a', $prod->encrypt('prod-a'));
update_option('options_hz_snap_common', $codec->encrypt('prod-common'));
delete_option('options_hz_snap_new');
delete_option('_options_hz_snap_new');

$result = EncryptionSnapshot::restore($repo, $codec, $path);
hz_check(in_array('options:0:options_hz_snap_a', $result['restored'], true), 'valeur illisible : restaurée');
hz_check($codec->decrypt((string) get_option('options_hz_snap_a')) === 'local-a', 'valeur restaurée = valeur locale');
hz_check(in_array('options:0:options_hz_snap_common', $result['kept'], true), 'valeur lisible : conservée');
hz_check($codec->decrypt((string) get_option('options_hz_snap_common')) === 'prod-common', 'valeur lisible : non écrasée');
hz_check($codec->decrypt((string) get_option('options_hz_snap_new')) === 'local-new', 'valeur absente : recréée');
hz_check(get_option('_options_hz_snap_new') === 'field_hz_snap_new', 'valeur absente : référence ACF recréée');

$again = EncryptionSnapshot::restore($repo, $codec, $path);
hz_check($again['restored'] === [], 'restauration idempotente');

hz_check(EncryptionSnapshot::restore($repo, $prod, $path)['restored'] === [], 'autre clé : rien restauré (instantané illisible)');

foreach (['a', 'common', 'new'] as $suffix) {
    delete_option('options_hz_snap_' . $suffix);
    delete_option('_options_hz_snap_' . $suffix);
}
unlink($path);
hz_done();
