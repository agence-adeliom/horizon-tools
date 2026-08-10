<?php

declare(strict_types=1);

namespace Adeliom\HorizonTools\Hooks;

use Adeliom\HorizonTools\Services\CssService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Vite;

class DefaultGutenbergHooks extends AbstractHook
{
    public function init(): void
    {
        add_filter('block_editor_settings_all', [$this, 'injectEditorStyles']);

        if (!Config::get('gutenberg.block_directory', false)) {
            $this->disableBlockDirectory();
        }
    }

    /**
     * Remove the Block Directory from the block inserter.
     *
     * Without it, searching in the inserter lists plugins to install from
     * wordpress.org next to the site's own blocks — never desirable on a
     * project where the available blocks are the ones we ship.
     *
     * Re-enable per project via Config::set('gutenberg.block_directory', true).
     */
    public function disableBlockDirectory(): void
    {
        remove_action('enqueue_block_editor_assets', 'wp_enqueue_editor_block_directory_assets');
    }

    /**
     * Inject theme styles into the Gutenberg iframe.
     *
     * In hot mode: @import url() so Vite HMR keeps styles live.
     * In production: inlined content with unlayered Tailwind utilities so class
     * selectors beat unlayered WP base styles inside the iframe.
     *
     * Configurable via Config::get('gutenberg.styles', [...defaults...]).
     */
    public function injectEditorStyles(array $settings): array
    {
        $isHot = Vite::isRunningHot();
        $handles = Config::get('gutenberg.styles', ['resources/styles/app.css', 'resources/styles/editor.css']);

        foreach ($handles as $handle) {
            try {
                $url = Vite::asset($handle);
                if ($isHot) {
                    $css = "@import url('$url')";
                } else {
                    $css = CssService::prepareForAdmin(Vite::content($handle));
                }
                $settings['styles'][] = ['css' => $css];
            } catch (\Throwable) {
                // Asset not in manifest — skip silently
            }
        }

        return $settings;
    }
}
