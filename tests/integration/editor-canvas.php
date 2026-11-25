<?php

// Pas de declare(strict_types=1) : WP-CLI eval-file le refuse (« must be the very first statement »).

use Livewire\Mechanisms\FrontendAssets\FrontendAssets as LivewireFrontendAssets;

require __DIR__ . '/bootstrap.php';

/*
 * Loader Livewire/Alpine de l'éditeur de blocs : document parent et iframe du canevas.
 *
 * Rejoue les 2 ordres d'appel du cœur :
 * - éditeur d'articles (wp-admin/edit-form-blocks.php) : enqueue_block_editor_assets (parent), puis
 *   _wp_get_iframed_editor_assets() (iframe, via enqueue_block_assets), puis admin_enqueue_scripts
 *   dans admin-header.php, qui rejoue enqueue_block_assets dans le parent ;
 * - éditeur de site et éditeur de widgets : la collecte de l'iframe a lieu avant
 *   enqueue_block_editor_assets.
 * Le parent doit garder un seul loader, intact. L'iframe doit recevoir le sien avec
 * window.livewireScriptConfig.
 */

const HZ_LOADER = 'horizon-block-editor-loader';
const HZ_LOADER_MARKER = 'Block editor Livewire/Alpine loader.';
const HZ_SCRIPT_CONFIG = 'window.livewireScriptConfig = {';

if (!class_exists(LivewireFrontendAssets::class)) {
    echo 'Livewire absent : contrôle sans objet.' . PHP_EOL;
    exit(0);
}

require_once ABSPATH . 'wp-admin/includes/class-wp-screen.php';
require_once ABSPATH . 'wp-admin/includes/screen.php';

/** Repart d'un parent sans loader, pour enchaîner les scénarios. */
function hz_reset_loader(): void
{
    wp_dequeue_script(HZ_LOADER);
    wp_deregister_script(HZ_LOADER);
}

function hz_block_editor_screen(string $hookName): void
{
    set_current_screen($hookName);
    get_current_screen()->is_block_editor(true);
}

/** Contrôle le HTML des scripts collectés pour l'iframe. */
function hz_check_iframe(string $scripts, string $context): void
{
    hz_check(substr_count($scripts, HZ_LOADER_MARKER) === 1, "$context, iframe : loader présent une seule fois");
    hz_check(
        substr_count($scripts, 'window.blockEditorLoaderConfig =') === 1,
        "$context, iframe : blockEditorLoaderConfig présent une seule fois"
    );
    hz_check(substr_count($scripts, HZ_SCRIPT_CONFIG) === 1, "$context, iframe : livewireScriptConfig présent une seule fois");

    $configPos = strpos($scripts, HZ_SCRIPT_CONFIG);
    $loaderPos = strpos($scripts, HZ_LOADER_MARKER);
    hz_check(
        $configPos !== false && $loaderPos !== false && $configPos < $loaderPos,
        "$context, iframe : livewireScriptConfig imprimé avant le loader"
    );
}

/** Contrôle le loader du document parent, tel qu'il sera imprimé. */
function hz_check_parent(array $expectedBefore, string $context): void
{
    hz_check(wp_script_is(HZ_LOADER), "$context, parent : loader en file");
    hz_check(
        (array) wp_scripts()->get_data(HZ_LOADER, 'before') === $expectedBefore,
        "$context, parent : scripts inline du loader inchangés"
    );

    ob_start();
    wp_scripts()->do_items([HZ_LOADER]);
    $scripts = (string) ob_get_clean();
    wp_scripts()->done = array_diff(wp_scripts()->done, [HZ_LOADER]);

    hz_check(substr_count($scripts, HZ_LOADER_MARKER) === 1, "$context, parent : loader imprimé une seule fois");
    hz_check(
        substr_count($scripts, HZ_SCRIPT_CONFIG) === 0,
        "$context, parent : pas de livewireScriptConfig doublé (admin_head s’en charge)"
    );
}

/**
 * Rejoue admin_enqueue_scripts (admin-header.php) et vérifie qu'enqueue_block_assets y est bien passé.
 *
 * Seul le callback du cœur qui relaie vers enqueue_block_assets (wp_common_block_scripts_and_styles)
 * reste branché : hors requête d'admin, les callbacks des extensions peuvent échouer (Smush lève une
 * erreur fatale en WP-CLI). doing_action('admin_enqueue_scripts') reste vrai, comme dans l'admin.
 */
function hz_admin_enqueue_scripts(string $hookSuffix, string $context): void
{
    global $wp_filter;

    $savedHook = $wp_filter['admin_enqueue_scripts'] ?? null;
    $wp_filter['admin_enqueue_scripts'] = new WP_Hook();
    add_action('admin_enqueue_scripts', 'wp_common_block_scripts_and_styles');

    $before = did_action('enqueue_block_assets');
    do_action('admin_enqueue_scripts', $hookSuffix);

    if ($savedHook === null) {
        unset($wp_filter['admin_enqueue_scripts']);
    } else {
        $wp_filter['admin_enqueue_scripts'] = $savedHook;
    }

    hz_check(
        did_action('enqueue_block_assets') > $before,
        "$context : admin_enqueue_scripts a déclenché enqueue_block_assets dans le parent"
    );
}

// Hors écran d'admin (front, WP-CLI) : enqueue_block_assets ne doit rien ajouter.
do_action('enqueue_block_assets');
hz_check(!wp_script_is(HZ_LOADER), 'Front : loader non mis en file sur enqueue_block_assets');

// Écran d'admin hors éditeur de blocs : rien non plus.
set_current_screen('dashboard');
hz_check(is_admin() && !get_current_screen()->is_block_editor(), 'Tableau de bord simulé : admin hors éditeur de blocs');
do_action('enqueue_block_assets');
hz_check(!wp_script_is(HZ_LOADER), 'Tableau de bord : loader non mis en file sur enqueue_block_assets');

// Éditeur d'articles : parent, collecte de l'iframe, puis admin_enqueue_scripts.
$context = 'Éditeur d’articles';
hz_block_editor_screen('post');
hz_check(is_admin() && get_current_screen()->is_block_editor(), "$context : écran simulé (admin, éditeur de blocs)");

do_action('enqueue_block_editor_assets');
hz_check(wp_script_is(HZ_LOADER), "$context, parent : loader mis en file sur enqueue_block_editor_assets");
$parentBefore = (array) wp_scripts()->get_data(HZ_LOADER, 'before');

$assets = _wp_get_iframed_editor_assets();
hz_check_iframe((string) $assets['scripts'], $context);

hz_admin_enqueue_scripts('post.php', $context);
hz_check_parent($parentBefore, $context);

// Éditeur de site (même ordre pour l'éditeur de widgets) : collecte de l'iframe avant le parent.
$context = 'Éditeur de site';
hz_reset_loader();
hz_block_editor_screen('site-editor');

$assets = _wp_get_iframed_editor_assets();
hz_check_iframe((string) $assets['scripts'], $context);
hz_check(!wp_script_is(HZ_LOADER, 'registered'), "$context, parent : loader absent après la seule collecte de l’iframe");

do_action('enqueue_block_editor_assets');
hz_check(wp_script_is(HZ_LOADER), "$context, parent : loader mis en file sur enqueue_block_editor_assets");
$parentBefore = (array) wp_scripts()->get_data(HZ_LOADER, 'before');

hz_admin_enqueue_scripts('site-editor.php', $context);
hz_check_parent($parentBefore, $context);

hz_done();
