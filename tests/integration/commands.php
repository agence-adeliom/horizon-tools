<?php

// Pas de declare(strict_types=1) : WP-CLI eval-file le refuse (« must be the very first statement »).

use Adeliom\HorizonTools\Console\Listeners\PreventKeyGenerate;
use Adeliom\HorizonTools\Services\Encryption\EncryptedValueCodec;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\BufferedOutput;

require __DIR__ . '/bootstrap.php';

/*
 * Commandes encryption:snapshot, encryption:rotate et encryption:status lancées par Artisan,
 * et blocage de key:generate.
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

/*
 * F1 : key:generate refusé tant que des valeurs lisibles existent.
 * Triple garde-fou, car la commande d’Acorn ajoute une ligne APP_KEY en tête du fichier
 * d’environnement quand elle ne trouve pas la clé courante :
 * - clé injectée en configuration (jamais celle du .env) ;
 * - fichier d’environnement redirigé vers un fichier temporaire ;
 * - la commande réelle n’est lancée que si le listener seul a bien refusé.
 */
$savedKey = config('app.key');
$savedEnvFile = app()->environmentFile();
$guardEnv = sys_get_temp_dir() . '/hz-keygen-guard.env';
$guardContents = "APP_KEY=garde\n";
file_put_contents($guardEnv, $guardContents);
// environmentFilePath() = environmentPath() . '/' . environmentFile() : on remonte jusqu’à la racine.
app()->loadEnvironmentFrom(str_repeat('../', substr_count(trim(app()->environmentPath(), '/'), '/') + 1) . ltrim($guardEnv, '/'));
$redirected = realpath(app()->environmentFilePath()) === realpath($guardEnv);
hz_check($redirected, 'F1 : fichier d’environnement redirigé vers un fichier temporaire');

config(['app.key' => 'base64:' . base64_encode(Encrypter::generateKey('AES-256-CBC'))]);
app()->forgetInstance('encrypter');
update_option('hz_cmd_keygen', EncryptedValueCodec::make()->encrypt('valeur-lisible'), false);

$listener = app(PreventKeyGenerate::class);
$refused = false;

try {
    $listener->handle(new CommandStarting('key:generate', new ArrayInput([]), new BufferedOutput()));
} catch (\RuntimeException $e) {
    $refused = str_contains($e->getMessage(), 'key:generate refusé') && str_contains($e->getMessage(), 'APP_PREVIOUS_KEYS');
}
hz_check($refused, 'F1 : listener -> exception, message conservé');

$showAllowed = true;

try {
    $listener->handle(new CommandStarting('key:generate', new ArrayInput(['--show' => true]), new BufferedOutput()));
} catch (\Throwable) {
    $showAllowed = false;
}
hz_check($showAllowed, 'F1 : listener -> --show permis');

if ($refused && $redirected) {
    $output = new BufferedOutput();
    $code = app(Kernel::class)->handle(new StringInput('key:generate'), $output);
    hz_check($code === 1 && str_contains($output->fetch(), 'key:generate refusé'), 'F1 : key:generate -> refusé, message affiché, code 1');
    hz_check(file_get_contents($guardEnv) === $guardContents, 'F1 : key:generate non exécuté (fichier d’environnement intact)');

    $output = new BufferedOutput();
    $code = app(Kernel::class)->handle(new StringInput('key:generate --show'), $output);
    hz_check($code === 0 && str_starts_with(trim($output->fetch()), 'base64:'), 'F1 : key:generate --show -> clé affichée, code 0');
} else {
    hz_check(false, 'F1 : commande réelle non lancée (garde-fou)');
}

delete_option('hz_cmd_keygen');
config(['app.key' => $savedKey]);
app()->forgetInstance('encrypter');
app()->loadEnvironmentFrom($savedEnvFile);
unlink($guardEnv);
hz_done();
