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
 */
(function () {
    var config = window.blockEditorLoaderConfig || {};
    var blockUrls = config.blockUrls || [];
    var livewireUrl = config.livewireUrl || '';
    var csrfTokenUrl = config.csrfTokenUrl || '';
    var viteClientUrl = config.viteClientUrl || '';

    if (!livewireUrl) {
        return;
    }

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