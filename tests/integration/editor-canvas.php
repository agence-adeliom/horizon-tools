<?php

// Pas de declare(strict_types=1) : WP-CLI eval-file le refuse (« must be the very first statement »).

use Livewire\Mechanisms\FrontendAssets\FrontendAssets as LivewireFrontendAssets;

require __DIR__ . '/bootstrap.php';

/*
 * Loader Livewire/Alpine de l'éditeur de blocs : document parent et iframe du canevas.
 *
 * Rejoue l'ordre de wp-admin/edit-form-blocks.php : enqueue_block_editor_assets (parent), puis
 * _wp_get_iframed_editor_assets() (iframe, via enqueue_block_assets). Le parent doit garder un
 * seul loader, l'iframe doit recevoir le sien avec window.livewireScriptConfig.
 */

const HZ_LOADER = 'horizon-block-editor-loader';
const HZ_LOADER_MARKER = 'Block editor Livewire/Alpine loader.';

if (!class_exists(LivewireFrontendAssets::class)) {
    echo 'Livewire absent : contrôle sans objet.' . PHP_EOL;
    exit(0);
}

require_once ABSPATH . 'wp-admin/includes/class-wp-screen.php';
require_once ABSPATH . 'wp-admin/includes/screen.php';

function hz_count(string $haystack, string $needle): int
{
    return substr_count($haystack, $needle);
}

// Hors écran d'admin (front, WP-CLI) : enqueue_block_assets ne doit rien ajouter.
do_action('enqueue_block_assets');
hz_check(!wp_script_is(HZ_LOADER), 'Front : loader non mis en file sur enqueue_block_assets');

// Écran de l'éditeur de blocs (is_admin() suit $current_screen->in_admin()).
set_current_screen('post');
get_current_screen()->is_block_editor(true);
hz_check(is_admin() && get_current_screen()->is_block_editor(), 'Écran simulé : admin et éditeur de blocs');

do_action('enqueue_block_editor_assets');
hz_check(wp_script_is(HZ_LOADER), 'Parent : loader mis en file sur enqueue_block_editor_assets');
$parentBefore = (array) wp_scripts()->get_data(HZ_LOADER, 'before');

// Collecte des assets de l'iframe.
$assets = _wp_get_iframed_editor_assets();
$iframeScripts = (string) $assets['scripts'];

hz_check(hz_count($iframeScripts, HZ_LOADER_MARKER) === 1, 'Iframe : loader présent une seule fois');
hz_check(hz_count($iframeScripts, 'window.blockEditorLoaderConfig =') === 1, 'Iframe : blockEditorLoaderConfig présent une seule fois');
hz_check(hz_count($iframeScripts, 'window.livewireScriptConfig = {') === 1, 'Iframe : livewireScriptConfig présent');
hz_check(
    strpos($iframeScripts, 'window.livewireScriptConfig = {') < strpos($iframeScripts, HZ_LOADER_MARKER),
    'Iframe : livewireScriptConfig imprimé avant le loader'
);

// Le parent ne doit pas avoir été modifié par la collecte de l'iframe.
hz_check(wp_script_is(HZ_LOADER), 'Parent : loader toujours en file après la collecte de l’iframe');
hz_check((array) wp_scripts()->get_data(HZ_LOADER, 'before') === $parentBefore, 'Parent : scripts inline du loader inchangés');

ob_start();
wp_scripts()->do_items([HZ_LOADER]);
$parentScripts = (string) ob_get_clean();
hz_check(hz_count($parentScripts, HZ_LOADER_MARKER) === 1, 'Parent : loader imprimé une seule fois');
hz_check(hz_count($parentScripts, 'window.livewireScriptConfig = {') === 0, 'Parent : pas de livewireScriptConfig doublé (admin_head s’en charge)');

hz_done();
