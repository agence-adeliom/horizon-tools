/**
 * Block editor Livewire/Alpine loader.
 *
 * Reads window.blockEditorLoaderConfig (set via wp_add_inline_script 'before'):
 *   - blockUrls    {string[]} — ES module URLs for block scripts
 *   - livewireUrl  {string}   — Livewire script URL
 *   - csrfTokenUrl {string}   — Route that returns the CSRF token (web middleware)
 *   - viteClientUrl {string}  — Vite HMR client URL (hot mode only, may be empty)
 *
 * Load order guarantees:
 *   1. Fetch CSRF token and patch window.livewireScriptConfig.csrf
 *   2. Dynamic-import all block scripts (registers alpine:init listeners)
 *   3. Load Livewire script; on load: call Livewire.start()
 *   4. Alpine.initTree() on existing [wire:id] elements (setTimeout 0)
 *   5. MutationObserver re-runs initTree() on AJAX-injected [wire:id] elements
 *
 * Runs in the outer admin frame (non-iframed editor, WordPress 6.x) and in the editor canvas
 * iframe (always present since WordPress 7). Each document starts it at most once.
 *
 * Public signal for themes: window.horizonBlockEditor = { alpine: true, livewire: true } is set,
 * before anything starts, only in the documents where this loader starts Alpine and Livewire.
 * The flag announces that the loader starts them in this document (asynchronously): it does not
 * mean window.Alpine or window.Livewire is already available, so never call them on its strength.
 *
 * A theme handler that mounts by hand a component Alpine already handles (render_block_preview
 * in editor.ts, for instance) must read the flag in the block's own document, not in its own
 * window: theme handlers run in the outer frame, where the loader also starts on WordPress 7,
 * while the block may sit in the canvas iframe or in a preview iframe where nothing starts.
 *   if ($block[0]?.ownerDocument?.defaultView?.horizonBlockEditor?.alpine) return;
 * This stays correct in the non-iframed editor (WordPress 6.x) and with older horizon-tools.
 */
(function () {
    // One loader per document, even if a theme also replays it in the iframe.
    if (window.__horizonBlockEditorLoader) {
        return;
    }

    // Inserter and pattern previews are iframes too and receive the same assets:
    // only start in the editor canvas iframe (or in a top-level document).
    var frameName = null;
    try {
        frameName = window.frameElement ? window.frameElement.name : null;
    } catch (e) {}
    if (frameName !== null && frameName !== 'editor-canvas') {
        return;
    }

    window.__horizonBlockEditorLoader = true;

    var config = window.blockEditorLoaderConfig || {};
    var blockUrls = config.blockUrls || [];
    var livewireUrl = config.livewireUrl || '';
    var csrfTokenUrl = config.csrfTokenUrl || '';
    var viteClientUrl = config.viteClientUrl || '';

    if (!livewireUrl) {
        return;
    }

    // Alpine ships with Livewire: both are started by this loader in the current document.
    window.horizonBlockEditor = { alpine: true, livewire: true };

    if (viteClientUrl) {
        document.head.appendChild(
            Object.assign(document.createElement('script'), { type: 'module', src: viteClientUrl }),
        );
    }

    function initAlpineOn(el) {
        window.Alpine && window.Alpine.initTree && window.Alpine.initTree(el);
    }

    function loadLivewire() {
        var s = document.createElement('script');
        s.src = livewireUrl;
        s.onload = function () {
            window.Livewire && window.Livewire.start();

            // Elements already in DOM when Livewire.start() ran.
            setTimeout(function () {
                document.querySelectorAll('[wire\\:id]').forEach(initAlpineOn);
            }, 0);

            // Blocks loaded via ACF AJAX after Livewire is ready.
            new MutationObserver(function (mutations) {
                mutations.forEach(function (mutation) {
                    mutation.addedNodes.forEach(function (node) {
                        if (node.nodeType !== 1) {
                            return;
                        }
                        var targets = node.hasAttribute && node.hasAttribute('wire:id')
                            ? [node]
                            : node.querySelectorAll
                              ? Array.from(node.querySelectorAll('[wire\\:id]'))
                              : [];
                        if (!targets.length) {
                            return;
                        }
                        setTimeout(function () {
                            targets.forEach(initAlpineOn);
                        }, 0);
                    });
                });
            }).observe(document.body, { childList: true, subtree: true });
        };
        document.head.appendChild(s);
    }

    function run() {
        if (blockUrls.length) {
            Promise.all(blockUrls.map(function (u) {
                return import(u).catch(function () {});
            })).then(loadLivewire);
        } else {
            loadLivewire();
        }
    }

    fetch(csrfTokenUrl, { credentials: 'same-origin' })
        .then(function (r) { return r.ok ? r.text() : Promise.reject(); })
        .then(function (token) {
            if (token) {
                window.livewireScriptConfig = window.livewireScriptConfig || {};
                window.livewireScriptConfig.csrf = token;
            }
        })
        .catch(function () {})
        .finally(run);
})();