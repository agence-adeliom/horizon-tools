<?php

// Pas de declare(strict_types=1) : WP-CLI eval-file le refuse (« must be the very first statement »).

use Adeliom\HorizonTools\Services\Compilation\CompilationService;
use Adeliom\HorizonTools\ViewModels\Asset\AssetViewModel;
use Illuminate\Support\Facades\Vite;

require __DIR__ . '/bootstrap.php';

/*
 * Chargement des scripts compilés par AssetViewModel.
 *
 * Après un build Vite, chaque script JS du manifest doit sortir avec type="module", qu'il ait
 * des imports ou non. Chargés comme scripts classiques, 2 bundles minifiés de la même page
 * partagent la portée globale et le second plante (« Identifier 'x' has already been declared »).
 * Les noms de handle existants ne changent pas, et un script Bud (Sage 10) reste un script classique.
 */

/** Imprime le script du handle (sans ses dépendances) et le retire de la liste des scripts imprimés. */
function hz_script_tag(string $handle): string
{
    $scripts = wp_scripts();
    $savedDeps = $scripts->registered[$handle]->deps;
    $scripts->registered[$handle]->deps = [];

    ob_start();
    $scripts->do_items([$handle]);
    $html = (string) ob_get_clean();

    $scripts->registered[$handle]->deps = $savedDeps;
    $scripts->done = array_diff($scripts->done, [$handle]);

    // Seule la balise qui porte src= nous intéresse (les scripts inline before/after n'en ont pas).
    return preg_match('/<script[^>]*\bsrc=[^>]*>/', $html, $matches) ? $matches[0] : '';
}

/** Retrouve le handle sous lequel une URL a été mise en file. */
function hz_handle_for_url(string $url): ?string
{
    foreach (wp_scripts()->registered as $handle => $script) {
        if ($script->src === $url && wp_script_is($handle)) {
            return $handle;
        }
    }

    return null;
}

function hz_check_module(string $handle, bool $expected, string $context): void
{
    $tag = hz_script_tag($handle);
    $count = substr_count($tag, 'type="module"');

    hz_check($tag !== '', "$context : balise <script src> imprimée");
    hz_check(
        $expected ? $count === 1 : $count === 0,
        sprintf('%s : %s', $context, $expected ? 'type="module" présent une seule fois' : 'script classique, sans type="module"')
    );
}

// 1. Entrées réelles du manifest du thème, après build.
if (!CompilationService::shouldUseVite()) {
    echo 'Projet Bud (Acorn 4) : entrées du manifest Vite sans objet.' . PHP_EOL;
} elseif (Vite::isRunningHot()) {
    echo 'Serveur Vite lancé (fichier hot) : entrées du manifest non contrôlées, lancer un build.' . PHP_EOL;
} else {
    foreach (['resources/scripts/app.ts', 'resources/scripts/editor.ts'] as $entry) {
        $asset = CompilationService::getAsset($entry);

        if (!$asset) {
            echo "  -     $entry absent du manifest : ignoré" . PHP_EOL;
            continue;
        }

        $asset->enqueueAll(dependencies: ['jquery']);
        $handle = hz_handle_for_url((string) $asset->getUrl());
        $context = sprintf('Manifest Vite, %s (%s)', $entry, empty($asset->getImports()) ? 'sans import' : 'avec imports');

        hz_check($handle !== null, "$context : script mis en file");

        if ($handle !== null) {
            hz_check_module($handle, true, $context);
        }
    }
}

// 2. Cas synthétiques : noms de handle et type selon le compilateur.
$context = 'Vite, entrée sans import';
$plain = (new AssetViewModel(file: 'assets/hz-test-plain.js', firstLevel: false))->setIsVite(true)->setIsBud(false);
$plain->enqueue();
hz_check(wp_script_is('assets/hz-test-plain.js'), "$context : handle inchangé (assets/hz-test-plain.js)");
hz_check_module('assets/hz-test-plain.js', true, $context);

$context = 'Vite, entrée avec imports';
$withImports = (new AssetViewModel(file: 'assets/hz-test-imports.js', firstLevel: false))->setIsVite(true)->setIsBud(false);
$withImports->addImport(new AssetViewModel(file: 'assets/hz-test-chunk.js', firstLevel: false));
$withImports->enqueue();
$handle = AssetViewModel::HANDLE_PREFIX_MODULE . 'assets/hz-test-imports.js';
hz_check(wp_script_is($handle), "$context : handle inchangé ($handle)");
hz_check_module($handle, true, $context);

$context = 'Vite, handle choisi par le thème';
$custom = (new AssetViewModel(file: 'assets/hz-test-custom.js', firstLevel: false))->setIsVite(true)->setIsBud(false);
$custom->enqueue(handle: 'hz-test-custom');
hz_check(wp_script_is('hz-test-custom'), "$context : handle respecté (hz-test-custom)");
hz_check_module('hz-test-custom', true, $context);

$context = 'Vite, serveur de développement';
$hot = new AssetViewModel(
    file: '/resources/scripts/hz-test-hot.ts',
    firstLevel: false,
    forceUrl: 'https://localhost:3001/resources/scripts/hz-test-hot.ts',
    isHot: true
);
$hot->setIsVite(true)->setIsBud(false);
$hot->enqueue();
$handle = AssetViewModel::HANDLE_PREFIX_VITE . '/resources/scripts/hz-test-hot.ts';
hz_check(wp_script_is($handle), "$context : handle inchangé ($handle)");
hz_check_module($handle, true, $context);

$context = 'Bud (Sage 10), entrée sans import';
$bud = (new AssetViewModel(file: 'scripts/hz-test-bud.js', firstLevel: false))->setIsVite(false)->setIsBud(true);
$bud->enqueue();
hz_check(wp_script_is('scripts/hz-test-bud.js'), "$context : handle inchangé (scripts/hz-test-bud.js)");
hz_check_module('scripts/hz-test-bud.js', false, $context);

$context = 'Script tiers mis en file par WordPress';
wp_enqueue_script('hz-test-tiers', 'https://example.com/hz-test-tiers.js');
hz_check_module('hz-test-tiers', false, $context);

hz_done();
