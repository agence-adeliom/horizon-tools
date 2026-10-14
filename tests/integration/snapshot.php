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

// I4 : ID instables. Une postmeta dont la ligne a disparu n’est jamais recréée.
$postId = wp_insert_post(['post_title' => 'hz snapshot', 'post_status' => 'draft']);
update_post_meta($postId, 'hz_snap_meta', $codec->encrypt('meta-locale'));
EncryptionSnapshot::capture($repo, $codec, $path);
delete_post_meta($postId, 'hz_snap_meta');
$metaResult = EncryptionSnapshot::restore($repo, $codec, $path);
$metaLocation = sprintf('postmeta:%d:hz_snap_meta', $postId);
hz_check(
    in_array($metaLocation, $metaResult['skipped'], true) && !in_array($metaLocation, $metaResult['restored'], true),
    'I4 : meta supprimée -> skipped'
);
hz_check(get_post_meta($postId, 'hz_snap_meta', true) === '', 'I4 : meta supprimée non recréée');
wp_delete_post($postId, true);

// I3 : le cache d’objets est vidé avant lecture (valeur périmée en cache).
update_option('options_hz_snap_cache', $codec->encrypt('cache-locale'));
EncryptionSnapshot::capture($repo, $codec, $path);
update_option('options_hz_snap_cache', $prod->encrypt('cache-prod'));
global $wpdb;
$wpdb->update($wpdb->options, ['option_value' => $codec->encrypt('cache-lisible')], ['option_name' => 'options_hz_snap_cache']);
$cacheResult = EncryptionSnapshot::restore($repo, $codec, $path);
hz_check(
    in_array('options:0:options_hz_snap_cache', $cacheResult['kept'], true),
    'I3 : lecture après vidage du cache (valeur lisible vue)'
);
delete_option('options_hz_snap_cache');

// I2 : les droits d’un dossier existant ne sont pas modifiés ; un dossier créé est en 0700.
$shared = sys_get_temp_dir() . '/hz-snapshot-shared';
if (!is_dir($shared)) {
    mkdir($shared, 0755);
}
chmod($shared, 0755);
EncryptionSnapshot::capture($repo, $codec, $shared . '/snap.json');
clearstatcache();
hz_check((fileperms($shared) & 0777) === 0755, 'I2 : dossier existant : droits inchangés');
hz_check((fileperms($shared . '/snap.json') & 0777) === 0600, 'I2 : fichier en 0600');
unlink($shared . '/snap.json');
rmdir($shared);
$fresh = sys_get_temp_dir() . '/hz-snapshot-fresh';
EncryptionSnapshot::capture($repo, $codec, $fresh . '/snap.json');
clearstatcache();
hz_check((fileperms($fresh) & 0777) === 0700, 'I2 : dossier créé : 0700');
unlink($fresh . '/snap.json');
rmdir($fresh);

// M3 : instantané vide sans clé ; suppression après restore sauf --keep.
EncryptionSnapshot::capture($repo, null, $path);
hz_check(json_decode((string) file_get_contents($path), true)['values'] === [], 'M3 : capture sans clé -> instantané vide');
hz_check(
    Illuminate\Support\Facades\Artisan::call('encryption:restore', ['--file' => $path, '--keep' => true]) === 0 && is_file($path),
    'M3 : restore --keep conserve le fichier'
);
hz_check(
    Illuminate\Support\Facades\Artisan::call('encryption:restore', ['--file' => $path]) === 0 && !is_file($path),
    'M3 : restore supprime le fichier'
);
EncryptionSnapshot::capture($repo, $codec, $path);

foreach (['a', 'common', 'new'] as $suffix) {
    delete_option('options_hz_snap_' . $suffix);
    delete_option('_options_hz_snap_' . $suffix);
}
if (is_file($path)) {
    unlink($path);
}
rmdir($dir);
hz_done();
