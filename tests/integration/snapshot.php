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
$dir = sys_get_temp_dir() . '/hz-snapshot-check';
$path = $dir . '/snapshot.json';

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

// Instantané invalide ou étranger : rien ne doit être écrit
$bad = sys_get_temp_dir() . '/hz-snapshot-bad.json';
$entry = ['table' => 'options', 'objectId' => 0, 'key' => 'options_hz_snap_plain', 'value' => 'secret-en-clair', 'reference' => null];
file_put_contents($bad, json_encode(['version' => 1, 'values' => [$entry]]));
$threw = false;
try {
    EncryptionSnapshot::restore($repo, $codec, $bad);
} catch (\RuntimeException) {
    $threw = true;
}
hz_check($threw && get_option('options_hz_snap_plain') === false, 'entrée en clair : exception, rien d’écrit');

file_put_contents($bad, '{');
$threw = false;
try {
    EncryptionSnapshot::restore($repo, $codec, $bad);
} catch (\JsonException) {
    $threw = true;
}
hz_check($threw, 'JSON mal formé : exception');

$ghost = [
    'table' => 'postmeta',
    'objectId' => 999999999,
    'key' => 'hz_snap_ghost',
    'value' => $codec->encrypt('fantome'),
    'reference' => null,
];
file_put_contents($bad, json_encode(['version' => 1, 'values' => [$ghost]]));
$ghostResult = EncryptionSnapshot::restore($repo, $codec, $bad);
hz_check($ghostResult['skipped'] === ['postmeta:999999999:hz_snap_ghost'] && $ghostResult['restored'] === [], 'objet absent : ignoré');
unlink($bad);

foreach (['a', 'common', 'new'] as $suffix) {
    delete_option('options_hz_snap_' . $suffix);
    delete_option('_options_hz_snap_' . $suffix);
}
unlink($path);
rmdir($dir);
hz_done();
