<?php

declare(strict_types=1);

namespace Adeliom\HorizonTools\Hooks;

use Adeliom\HorizonTools\Services\Compilation\CompilationService;
use Adeliom\HorizonTools\ViewModels\Asset\AssetViewModel;
use Illuminate\Support\Facades\Vite;
use Livewire\Mechanisms\FrontendAssets\FrontendAssets as LivewireFrontendAssets;

class DefaultBackOfficeHooks extends AbstractHook
{
    private const LOADER_HANDLE = 'horizon-block-editor-loader';

    public function init(): void
    {
        add_action('admin_head', [$this, 'injectLivewireStyles']);
        add_action('admin_head', [$this, 'injectLivewireScriptConfig']);
        add_action('enqueue_block_editor_assets', [$this, 'enqueueBlockEditorAssets']);
        add_action('enqueue_block_assets', [$this, 'enqueueBlockCanvasAssets']);
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
     * Enqueue block CSS assets and the Livewire/Alpine loader in the current asset collection.
     *
     * Called on enqueue_block_editor_assets (outer admin frame), where ACF block previews render
     * when the editor is not iframed (WordPress 6.x with blocks below API version 3). Since
     * WordPress 7, the canvas is always an iframe: enqueueBlockCanvasAssets() replays this
     * method for the iframe document.
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
                foreach (self::collectStyleAssets($asset) as $cssAsset) {
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

            wp_register_script(self::LOADER_HANDLE, false, [], null, true);
            wp_add_inline_script(
                self::LOADER_HANDLE,
                'window.blockEditorLoaderConfig = ' . wp_json_encode($loaderConfig) . ';',
                'before'
            );
            wp_add_inline_script(self::LOADER_HANDLE, $loaderJs);
            wp_enqueue_script(self::LOADER_HANDLE);
        } catch (\Throwable) {
            // Livewire not fully available — CSS already enqueued above
        }
    }

    /**
     * Replay the block editor assets inside the editor canvas iframe.
     *
     * The iframe only receives what is enqueued on enqueue_block_assets, collected by
     * _wp_get_iframed_editor_assets() in a separate WP_Scripts/WP_Styles queue. That action also
     * fires on the front end and in the outer admin document (admin_enqueue_scripts): only the
     * iframe collection of a block editor screen is kept.
     */
    public static function enqueueBlockCanvasAssets(): void
    {
        if (!is_admin() || doing_action('admin_enqueue_scripts') || !function_exists('get_current_screen')) {
            return;
        }

        if (!get_current_screen()?->is_block_editor()) {
            return;
        }

        // The iframe collection shares the outer frame's registered script objects: without this,
        // wp_add_inline_script would also alter the outer loader (inline scripts printed twice) and
        // wp_register_script would be a no-op. Deregistering only drops the key from the iframe copy.
        wp_deregister_script(self::LOADER_HANDLE);
        self::enqueueBlockEditorAssets();

        // window.livewireScriptConfig is only printed in the outer frame (admin_head): the loader
        // needs it in the iframe document too.
        if (wp_script_is(self::LOADER_HANDLE) && class_exists(LivewireFrontendAssets::class)) {
            wp_add_inline_script(self::LOADER_HANDLE, trim(strip_tags(LivewireFrontendAssets::scriptConfig())), 'before');
        }
    }

    /**
     * Collect the CSS assets of a script, including those attached to the JS chunks it imports.
     *
     * When several entries import the same library (e.g. Swiper), Vite extracts it into a shared
     * chunk and attaches its CSS to that chunk in the manifest, not to the importing entries.
     *
     * @param array<string, true> $visited
     * @return AssetViewModel[]
     */
    private static function collectStyleAssets(AssetViewModel $asset, array &$visited = []): array
    {
        $styles = [];

        foreach ($asset->getAssociatedAssets() ?? [] as $associatedAsset) {
            if ($associatedAsset->isStyle()) {
                $styles[] = $associatedAsset;
                continue;
            }

            $url = $associatedAsset->getUrl();
            if (!$url || isset($visited[$url])) {
                continue;
            }
            $visited[$url] = true;

            array_push($styles, ...self::collectStyleAssets($associatedAsset, $visited));
        }

        return $styles;
    }
}
