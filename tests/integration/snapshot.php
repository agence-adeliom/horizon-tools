<?php

// Pas de declare(strict_types=1) : WP-CLI eval-file le refuse (« must be the very first statement »).

use Adeliom\HorizonTools\Services\Encryption\EncryptedValueCodec;
use Adeliom\HorizonTools\Services\Encryption\EncryptedValueRepository;
use Adeliom\HorizonTools\Services\Encryption\SnapshotStore;
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

hz_check(SnapshotStore::capture($repo, $codec, $path) >= 3, 'capture : au moins 3 valeurs sauvegardées');
hz_check(!str_contains((string) file_get_contents($path), 'local-a'), 'instantané : aucune valeur en clair');

// Simulation de l'import : valeur prod illisible, valeur prod lisible (clé commune), valeur absente en prod
update_option('options_hz_snap_a', $prod->encrypt('prod-a'));
update_option('options_hz_snap_common', $codec->encrypt('prod-common'));
delete_option('options_hz_snap_new');
delete_option('_options_hz_snap_new');

$result = SnapshotStore::restore($repo, $codec, $path);
hz_check(in_array('options:0:options_hz_snap_a', $result['restored'], true), 'valeur illisible : restaurée');
hz_check($codec->decrypt((string) get_option('options_hz_snap_a')) === 'local-a', 'valeur restaurée = valeur locale');
hz_check(in_array('options:0:options_hz_snap_common', $result['kept'], true), 'valeur lisible : conservée');
hz_check($codec->decrypt((string) get_option('options_hz_snap_common')) === 'prod-common', 'valeur lisible : non écrasée');
hz_check($codec->decrypt((string) get_option('options_hz_snap_new')) === 'local-new', 'valeur absente : recréée');
hz_check(get_option('_options_hz_snap_new') === 'field_hz_snap_new', 'valeur absente : référence ACF recréée');

$again = SnapshotStore::restore($repo, $codec, $path);
hz_check($again['restored'] === [], 'restauration idempotente');

hz_check(SnapshotStore::restore($repo, $prod, $path)['restored'] === [], 'autre clé : rien restauré (instantané illisible)');

// Instantané invalide ou étranger : rien ne doit être écrit
$bad = sys_get_temp_dir() . '/hz-snapshot-bad.json';
$entry = ['table' => 'options', 'objectId' => 0, 'key' => 'options_hz_snap_plain', 'value' => 'secret-en-clair', 'reference' => null];
file_put_contents($bad, json_encode(['version' => 1, 'values' => [$entry]]));
$threw = false;
try {
    SnapshotStore::restore($repo, $codec, $bad);
} catch (\RuntimeException) {
    $threw = true;
}
hz_check($threw && get_option('options_hz_snap_plain') === false, 'entrée en clair : exception, rien d’écrit');

file_put_contents($bad, '{');
$threw = false;
try {
    SnapshotStore::restore($repo, $codec, $bad);
} catch (\RuntimeException $e) {
    $threw = str_starts_with($e->getMessage(), 'Instantané invalide (') && $e->getPrevious() instanceof \JsonException;
}
hz_check($threw, 'JSON mal formé : RuntimeException « Instantané invalide », JsonException en cause');
$code = Illuminate\Support\Facades\Artisan::call('encryption:restore', ['--file' => $bad]);
hz_check(
    $code === 1 && str_contains(Illuminate\Support\Facades\Artisan::output(), 'rien n’a été restauré'),
    'JSON mal formé : commande -> message, FAILURE'
);

$ghost = [
    'table' => 'postmeta',
    'objectId' => 999999999,
    'key' => 'hz_snap_ghost',
    'value' => $codec->encrypt('fantome'),
    'reference' => null,
];
file_put_contents($bad, json_encode(['version' => 1, 'values' => [$ghost]]));
$ghostResult = SnapshotStore::restore($repo, $codec, $bad);
hz_check($ghostResult['skipped'] === ['postmeta:999999999:hz_snap_ghost'] && $ghostResult['restored'] === [], 'objet absent : ignoré');
unlink($bad);

// I4 : ID instables. Une postmeta dont la ligne a disparu n’est jamais recréée.
$postId = wp_insert_post(['post_title' => 'hz snapshot', 'post_status' => 'draft']);
update_post_meta($postId, 'hz_snap_meta', $codec->encrypt('meta-locale'));
SnapshotStore::capture($repo, $codec, $path);
delete_post_meta($postId, 'hz_snap_meta');
$metaResult = SnapshotStore::restore($repo, $codec, $path);
$metaLocation = sprintf('postmeta:%d:hz_snap_meta', $postId);
hz_check(
    in_array($metaLocation, $metaResult['skipped'], true) && !in_array($metaLocation, $metaResult['restored'], true),
    'I4 : meta supprimée -> skipped'
);
hz_check(get_post_meta($postId, 'hz_snap_meta', true) === '', 'I4 : meta supprimée non recréée');
wp_delete_post($postId, true);

// I3 : le cache d’objets est vidé avant lecture (valeur périmée en cache).
update_option('options_hz_snap_cache', $codec->encrypt('cache-locale'));
SnapshotStore::capture($repo, $codec, $path);
update_option('options_hz_snap_cache', $prod->encrypt('cache-prod'));
global $wpdb;
$wpdb->update($wpdb->options, ['option_value' => $codec->encrypt('cache-lisible')], ['option_name' => 'options_hz_snap_cache']);
$cacheResult = SnapshotStore::restore($repo, $codec, $path);
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
SnapshotStore::capture($repo, $codec, $shared . '/snap.json');
clearstatcache();
hz_check((fileperms($shared) & 0777) === 0755, 'I2 : dossier existant : droits inchangés');
hz_check((fileperms($shared . '/snap.json') & 0777) === 0600, 'I2 : fichier en 0600');
unlink($shared . '/snap.json');
rmdir($shared);
$fresh = sys_get_temp_dir() . '/hz-snapshot-fresh';
SnapshotStore::capture($repo, $codec, $fresh . '/snap.json');
clearstatcache();
hz_check((fileperms($fresh) & 0777) === 0700, 'I2 : dossier créé : 0700');
unlink($fresh . '/snap.json');
rmdir($fresh);

// M3 : instantané vide sans clé ; suppression après restore sauf --keep.
SnapshotStore::capture($repo, null, $path);
hz_check(json_decode((string) file_get_contents($path), true)['values'] === [], 'M3 : capture sans clé -> instantané vide');
hz_check(
    Illuminate\Support\Facades\Artisan::call('encryption:restore', ['--file' => $path, '--keep' => true]) === 0 && is_file($path),
    'M3 : restore --keep conserve le fichier'
);
hz_check(
    Illuminate\Support\Facades\Artisan::call('encryption:restore', ['--file' => $path]) === 0 && !is_file($path),
    'M3 : restore supprime le fichier'
);
SnapshotStore::capture($repo, $codec, $path);

// Relecture finale I1 : une métadonnée n’est écrite que si elle est chiffrée ET illisible.
$metaPost = wp_insert_post(['post_title' => 'hz snapshot meta', 'post_status' => 'draft']);
update_post_meta($metaPost, 'hz_snap_empty', $codec->encrypt('meta-vide'));
update_post_meta($metaPost, 'hz_snap_plain', $codec->encrypt('meta-clair'));
update_post_meta($metaPost, 'hz_snap_lost', $codec->encrypt('meta-perdue'));
update_option('options_hz_snap_blank', $codec->encrypt('option-vide'));
$metaPath = $dir . '/meta.json';
SnapshotStore::capture($repo, $codec, $metaPath);
update_post_meta($metaPost, 'hz_snap_empty', '');
update_post_meta($metaPost, 'hz_snap_plain', 'valeur-en-clair');
update_post_meta($metaPost, 'hz_snap_lost', $prod->encrypt('meta-prod'));
update_option('options_hz_snap_blank', '');
$metaResult = SnapshotStore::restore($repo, $codec, $metaPath);
$at = static fn(string $key): string => sprintf('postmeta:%d:%s', $metaPost, $key);
hz_check(
    in_array($at('hz_snap_empty'), $metaResult['skipped'], true) && get_post_meta($metaPost, 'hz_snap_empty', true) === '',
    'I1 : meta vide sur un post existant -> skipped, pas d’écriture'
);
hz_check(
    in_array($at('hz_snap_plain'), $metaResult['kept'], true) && get_post_meta($metaPost, 'hz_snap_plain', true) === 'valeur-en-clair',
    'I1 : meta en clair -> kept, pas d’écriture'
);
hz_check(
    in_array($at('hz_snap_lost'), $metaResult['restored'], true) &&
        $codec->decrypt((string) get_post_meta($metaPost, 'hz_snap_lost', true)) === 'meta-perdue',
    'I1 : meta chiffrée illisible -> restaurée'
);
hz_check(
    in_array('options:0:options_hz_snap_blank', $metaResult['restored'], true) &&
        $codec->decrypt((string) get_option('options_hz_snap_blank')) === 'option-vide',
    'I1 : option vide -> restaurée (recréable)'
);
wp_delete_post($metaPost, true);
delete_option('options_hz_snap_blank');
unlink($metaPath);

// Écriture en échec pendant la restauration : entrée « failed », jamais comptée comme restaurée.
update_option('options_hz_snap_fail', $codec->encrypt('fail-locale'));
$failPath = $dir . '/fail.json';
SnapshotStore::capture($repo, $codec, $failPath);
update_option('options_hz_snap_fail', $prod->encrypt('fail-prod'));
$keepOld = static fn(mixed $new, mixed $old): mixed => $old;
add_filter('pre_update_option_options_hz_snap_fail', $keepOld, 10, 2);
$failResult = SnapshotStore::restore($repo, $codec, $failPath);
hz_check(
    in_array('options:0:options_hz_snap_fail', $failResult['failed'], true) &&
        !in_array('options:0:options_hz_snap_fail', $failResult['restored'], true),
    'écriture en échec -> failed, pas restored'
);
$code = Illuminate\Support\Facades\Artisan::call('encryption:restore', ['--file' => $failPath]);
$output = Illuminate\Support\Facades\Artisan::output();
remove_filter('pre_update_option_options_hz_snap_fail', $keepOld, 10);
hz_check(
    $code === 1 && str_contains($output, 'non écrite : options:0:options_hz_snap_fail') && is_file($failPath),
    'commande : écriture en échec -> listée, FAILURE, instantané conservé'
);
delete_option('options_hz_snap_fail');
if (is_file($failPath)) {
    unlink($failPath);
}

// Relecture finale I2 : des entrées ignorées -> l’instantané est conservé et son chemin affiché.
$ghostPath = $dir . '/ghost.json';
file_put_contents($ghostPath, json_encode(['version' => 1, 'values' => [$ghost]]));
$code = Illuminate\Support\Facades\Artisan::call('encryption:restore', ['--file' => $ghostPath]);
$output = Illuminate\Support\Facades\Artisan::output();
hz_check(
    $code === 0 && is_file($ghostPath) && str_contains($output, 'conservé') && str_contains($output, $ghostPath),
    'I2 : entrées ignorées -> instantané conservé, chemin affiché'
);
if (is_file($ghostPath)) {
    unlink($ghostPath);
}

// I2 : suppression impossible -> avertissement, la commande n’échoue pas.
$locked = sys_get_temp_dir() . '/hz-snapshot-locked';
if (!is_dir($locked)) {
    mkdir($locked, 0700);
}
SnapshotStore::capture($repo, $codec, $locked . '/snap.json');
chmod($locked, 0500);
$code = null;
$output = '';
try {
    $code = Illuminate\Support\Facades\Artisan::call('encryption:restore', ['--file' => $locked . '/snap.json']);
    $output = Illuminate\Support\Facades\Artisan::output();
} catch (\Throwable $e) {
    $output = get_class($e) . ' : ' . $e->getMessage();
}
chmod($locked, 0700);
hz_check($code === 0 && str_contains($output, 'Impossible de supprimer'), 'I2 : unlink en échec -> avertissement, SUCCESS');
if (is_file($locked . '/snap.json')) {
    unlink($locked . '/snap.json');
}
rmdir($locked);

foreach (['a', 'common', 'new'] as $suffix) {
    delete_option('options_hz_snap_' . $suffix);
    delete_option('_options_hz_snap_' . $suffix);
}
if (is_file($path)) {
    unlink($path);
}
rmdir($dir);
hz_done();
