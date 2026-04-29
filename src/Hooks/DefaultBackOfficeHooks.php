<?php

declare(strict_types=1);

namespace Adeliom\HorizonTools\Hooks;

use Adeliom\HorizonTools\Services\Compilation\CompilationService;
use Illuminate\Support\Facades\Vite;
use Livewire\Mechanisms\FrontendAssets\FrontendAssets as LivewireFrontendAssets;

class DefaultBackOfficeHooks extends AbstractHook
{
    public function init(): void
    {
        add_action('admin_head', [$this, 'injectLivewireStyles']);
        add_action('admin_head', [$this, 'injectLivewireScriptConfig']);
        add_action('enqueue_block_editor_assets', [$this, 'enqueueBlockEditorAssets']);
    }

    public static function injectLivewireStyles(): void
    {
        if (class_exists(LivewireFrontendAssets::class)) {
            echo LivewireFrontendAssets::styles();
        }
    }

    /**
     * Inject window.livewireScriptConfig into admin_head.
     *
     * The csrf field may be empty here because WP admin requests bypass Acorn's HTTP
     * kernel (StartSession never runs). The block-editor-loader script fetches the real
     * token from route('livewire.csrf-token') and patches the config before Livewire starts.
     */
    public static function injectLivewireScriptConfig(): void
    {
        if (class_exists(LivewireFrontendAssets::class)) {
            echo LivewireFrontendAssets::scriptConfig();
        }
    }

    /**
     * Enqueue block CSS assets and the Livewire/Alpine loader in the block editor outer frame.
     *
     * ACF block previews render in the outer admin frame (not the Gutenberg iframe).
     * enqueue_block_editor_assets targets exactly that context.
     *
     * CSS is always enqueued. The Livewire loader is only injected when Livewire is available.
     */
    public static function enqueueBlockEditorAssets(): void
    {
        $isHot = Vite::isRunningHot();
        $seenCssUrls = [];
        $blockScriptUrls = [];

        // Collect all JS/TS entry points except app.ts.
        // Production: read the Vite manifest (authoritative — covers blocks/ and components/).
        // Hot mode: scan the filesystem recursively (no manifest available).
        $scriptHandles = [];
        $manifestPath = get_template_directory() . '/public/build/manifest.json';

        if (!$isHot && file_exists($manifestPath)) {
            $manifest = json_decode(file_get_contents($manifestPath), true) ?? [];
            foreach ($manifest as $src => $entry) {
                if (empty($entry['isEntry']) || !str_starts_with($src, 'resources/scripts/')) {
                    continue;
                }
                if (str_ends_with($src, '.css') || $src === 'resources/scripts/app.ts') {
                    continue;
                }
                $scriptHandles[] = $src;
            }
        } else {
            $scriptsDir = get_template_directory() . '/resources/scripts/';
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($scriptsDir, \RecursiveDirectoryIterator::SKIP_DOTS)
            );
            foreach ($iterator as $file) {
                if (!$file->isFile() || !preg_match('/\.(ts|js)$/', $file->getFilename())) {
                    continue;
                }
                $handle = 'resources/scripts/' . str_replace($scriptsDir, '', $file->getPathname());
                if ($handle === 'resources/scripts/app.ts') {
                    continue;
                }
                $scriptHandles[] = $handle;
            }
        }

        foreach ($scriptHandles as $handle) {
            $asset = CompilationService::getAsset($handle);
            if (!$asset) {
                continue;
            }
            $blockScriptUrls[] = $asset->getUrl();
            // CSS chunks (e.g. Swiper) — hot mode: Vite HMR injects them on module load.
            if (!$isHot) {
                foreach ($asset->getAssociatedAssets() ?? [] as $cssAsset) {
                    if (!$cssAsset->isStyle()) {
                        continue;
                    }
                    $cssUrl = $cssAsset->getUrl();
                    if (!$cssUrl || isset($seenCssUrls[$cssUrl])) {
                        continue;
                    }
                    $seenCssUrls[$cssUrl] = true;
                    wp_enqueue_style('block-editor-css-' . md5($cssUrl), $cssUrl);
                }
            }
        }

        // Standalone block CSS entry points (resources/styles/blocks/*.css).
        foreach (glob(get_template_directory() . '/resources/styles/blocks/*.css') ?: [] as $file) {
            $asset = CompilationService::getAsset('resources/styles/blocks/' . basename($file));
            if (!$asset) {
                continue;
            }
            $cssUrl = $asset->getUrl();
            if (!$cssUrl || isset($seenCssUrls[$cssUrl])) {
                continue;
            }
            $seenCssUrls[$cssUrl] = true;
            wp_enqueue_style('block-editor-css-' . md5($cssUrl), $cssUrl);
        }

        if (!class_exists(LivewireFrontendAssets::class)) {
            return;
        }

        try {
            // Prefer the named route (absolute URL, Bedrock-safe). Fall back to parsing the
            // script tag — Bedrock serves from root but WP lives in /wp/, so relative paths
            // need home_url() to become absolute.
            try {
                $livewireUrl = route('livewire.js');
            } catch (\Throwable) {
                $scriptTag = LivewireFrontendAssets::js([]);
                preg_match('/\bsrc="([^"]+)"/', $scriptTag, $m);
                $livewireUrl = $m[1] ?? '';
                if ($livewireUrl && !str_starts_with($livewireUrl, 'http')) {
                    $livewireUrl = home_url($livewireUrl);
                }
            }

            if (!$livewireUrl) {
                return;
            }

            try {
                $csrfTokenUrl = route('livewire.csrf-token');
            } catch (\Throwable) {
                $csrfTokenUrl = home_url('/livewire/csrf-token');
            }

            $loaderConfig = [
                'blockUrls' => array_values($blockScriptUrls),
                'livewireUrl' => $livewireUrl,
                'csrfTokenUrl' => $csrfTokenUrl,
                'viteClientUrl' => $isHot ? Vite::asset('@vite/client') : '',
            ];

            $loaderJs = file_get_contents(__DIR__ . '/../../resources/scripts/block-editor-livewire-loader.js');

            wp_register_script('horizon-block-editor-loader', false, [], null, true);
            wp_add_inline_script(
                'horizon-block-editor-loader',
                'window.blockEditorLoaderConfig = ' . wp_json_encode($loaderConfig) . ';',
                'before'
            );
            wp_add_inline_script('horizon-block-editor-loader', $loaderJs);
            wp_enqueue_script('horizon-block-editor-loader');
        } catch (\Throwable) {
            // Livewire not fully available — CSS already enqueued above
        }
    }
}
