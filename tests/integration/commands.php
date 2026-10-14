<?php

// Pas de declare(strict_types=1) : WP-CLI eval-file le refuse (« must be the very first statement »).

use Adeliom\HorizonTools\Services\Encryption\EncryptedValueCodec;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\Artisan;

require __DIR__ . '/bootstrap.php';

/*
 * Commandes encryption:snapshot, encryption:rotate et encryption:status lancées par Artisan.
 * Attention : encryption:rotate rechiffre toutes les valeurs de la base avec APP_KEY.
 */

$codec = EncryptedValueCodec::make();
$foreign = new EncryptedValueCodec(new Encrypter(Encrypter::generateKey('AES-256-CBC'), 'AES-256-CBC'));

/** Lance une commande ; une exception non attrapée est rendue comme un code null. */
$run = static function (string $command, array $parameters = []): array {
    try {
        $code = Artisan::call($command, $parameters);

        return [$code, Artisan::output()];
    } catch (\Throwable $e) {
        return [null, get_class($e) . ' : ' . $e->getMessage()];
    }
};

// M4 : sans clé, encryption:snapshot ne bloque jamais, même si l’écriture de l’instantané échoue.
$notADirectory = sys_get_temp_dir() . '/hz-commands-file';
file_put_contents($notADirectory, 'x');
$savedKey = config('app.key');
config(['app.key' => '']);
app()->forgetInstance('encrypter');
[$code, $output] = $run('encryption:snapshot', ['--file' => $notADirectory . '/snap.json']);
config(['app.key' => $savedKey]);
app()->forgetInstance('encrypter');
hz_check($code === 0 && str_contains($output, 'APP_KEY absente'), 'M4 : sans clé, écriture impossible -> avertissement, SUCCESS');
[$code] = $run('encryption:snapshot', ['--file' => $notADirectory . '/snap.json']);
hz_check($code !== 0, 'M4 : avec clé, écriture impossible -> échec (comportement inchangé)');
unlink($notADirectory);

// M5 : rotate ne compte que les remplacements effectifs.
global $wpdb;

// a) jeton dont le clair est vide : encrypt('') renverrait '' et effacerait la valeur.
$emptyToken = EncryptedValueCodec::PREFIX . app('encrypter')->encryptString('');
update_option('hz_cmd_rot_empty', $emptyToken, false);

// b) option embarquée dont update_option() échoue (filtre pre_update_option qui renvoie l’ancienne valeur).
$widgetToken = $codec->encrypt('widget-rotation');
update_option('hz_cmd_rot_widget', [2 => ['content' => '<!-- wp:acf/x {"data":{"k":"' . $widgetToken . '"}} /-->']], false);
$blockOption = static fn(mixed $new, mixed $old): mixed => $old;
add_filter('pre_update_option_hz_cmd_rot_widget', $blockOption, 10, 2);

// c) contenu dont l’UPDATE échoue (requête annulée par le filtre « query »).
$contentToken = $codec->encrypt('contenu-rotation');
$postId = wp_insert_post([
    'post_title' => 'hz-commands-rotate',
    'post_status' => 'draft',
    'post_content' => '<!-- wp:acf/x {"data":{"k":"' . $contentToken . '"}} /-->',
]);
$blockUpdate = static function (string $query) use ($wpdb, $postId): string {
    return str_starts_with($query, 'UPDATE `' . $wpdb->posts . '`') && str_contains($query, '`ID` = ' . $postId) ? '' : $query;
};
add_filter('query', $blockUpdate);

// Valeurs déjà en base (autres que les trois cas ci-dessus, supposées lisibles) : seules elles doivent être comptées.
$repo = new Adeliom\HorizonTools\Services\Encryption\EncryptedValueRepository();
$expected = count($repo->all()) + count($repo->contentTokens()) + count($repo->embeddedOptionTokens()) - 3;

[$code, $output] = $run('encryption:rotate');

remove_filter('query', $blockUpdate);
remove_filter('pre_update_option_hz_cmd_rot_widget', $blockOption, 10);

hz_check($code === 1, 'M5 : remplacement en échec -> FAILURE');
hz_check(get_option('hz_cmd_rot_empty') === $emptyToken, 'M5 : jeton au clair vide -> non réécrit');
hz_check(str_contains($output, 'options:0:hz_cmd_rot_empty'), 'M5 : jeton au clair vide -> listé');
hz_check(str_contains($output, 'options:0:hz_cmd_rot_widget (contenu)'), 'M5 : option embarquée non remplacée -> listée');
hz_check(str_contains($output, sprintf('posts:%d:post_content', $postId)), 'M5 : contenu non remplacé -> listé');
hz_check(
    preg_match('/(\d+) valeur\(s\) rechiffrée/', $output, $count) === 1 && (int) $count[1] === $expected,
    'M5 : échecs non comptés comme rechiffrés'
);

delete_option('hz_cmd_rot_empty');
delete_option('hz_cmd_rot_widget');

// M6 : conseil de status selon le type de valeurs illisibles.
$wpdb->update(
    $wpdb->posts,
    ['post_content' => '<!-- wp:acf/x {"data":{"k":"' . $foreign->encrypt('perdu') . '"}} /-->'],
    ['ID' => $postId]
);
clean_post_cache($postId);
[, $output] = $run('encryption:status');
hz_check(
    !str_contains($output, 'encryption:restore') && str_contains($output, 'APP_PREVIOUS_KEYS'),
    'M6 : jetons de contenu illisibles -> ni restore conseillé, ancienne clé suggérée'
);

update_option('options_hz_cmd_status', $foreign->encrypt('perdu'), false);
[, $output] = $run('encryption:status');
hz_check(str_contains($output, 'encryption:restore'), 'M6 : option illisible -> restore conseillé');
delete_option('options_hz_cmd_status');

wp_delete_post($postId, true);
hz_done();
