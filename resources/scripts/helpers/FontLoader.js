/**
 * FontLoader - Utilitaire pour attendre que les fonts soient vraiment chargées
 *
 * Usage :
 * - FontLoader.waitForFonts(['Ubuntu', 'Cabin']).then(() => { ... })
 * - FontLoader.waitForAll().then(() => { ... })
 * - FontLoader.onReady(() => { ... }, { fonts: ['Ubuntu'], timeout: 3000 })
 */
class FontLoader {
    /**
     * Attendre des fonts spécifiques
     * @param {string[]} fontFamilies - Liste des noms de fonts à attendre
     * @param {Object} options - Options de configuration
     * @param {number} options.timeout - Timeout en ms (défaut: 2000)
     * @param {number} options.checkInterval - Intervalle de vérification en ms (défaut: 50)
     * @param {number} options.fallbackDelay - Délai de fallback si pas de support (défaut: 300)
     * @returns {Promise<void>}
     */
    static async waitForFonts(fontFamilies = [], options = {}) {
        const { timeout = 2000, checkInterval = 50, fallbackDelay = 300 } = options;

        // Si pas de fonts spécifiées, on attend juste document.fonts.ready
        if (fontFamilies.length === 0) {
            return this.waitForAll(options);
        }

        // Fallback pour navigateurs sans support
        if (!document.fonts) {
            return new Promise(resolve => setTimeout(resolve, fallbackDelay));
        }

        // Attendre d'abord que l'API soit prête
        await document.fonts.ready;

        // Vérifier si toutes les fonts sont déjà là
        const allLoaded = fontFamilies.every(family => document.fonts.check(`16px "${family}"`));

        if (allLoaded) {
            return Promise.resolve();
        }

        // Sinon, polling jusqu'à ce qu'elles soient chargées
        return new Promise(resolve => {
            const startTime = Date.now();

            const checkFonts = () => {
                const allLoaded = fontFamilies.every(family => document.fonts.check(`16px "${family}"`));

                if (allLoaded || Date.now() - startTime > timeout) {
                    resolve();
                } else {
                    setTimeout(checkFonts, checkInterval);
                }
            };

            checkFonts();
        });
    }

    /**
     * Attendre que toutes les fonts du document soient chargées
     * @param {Object} options - Options de configuration
     * @returns {Promise<void>}
     */
    static async waitForAll(options = {}) {
        const { fallbackDelay = 300 } = options;

        if (!document.fonts) {
            return new Promise(resolve => setTimeout(resolve, fallbackDelay));
        }

        return document.fonts.ready;
    }

    /**
     * Callback quand les fonts sont prêtes (syntaxe plus simple)
     * @param {Function} callback - Fonction à exécuter
     * @param {Object} options - Options (fonts, timeout, etc.)
     */
    static onReady(callback, options = {}) {
        const { fonts = [] } = options;

        this.waitForFonts(fonts, options)
            .then(callback)
            .catch(() => {
                console.warn('FontLoader: Timeout atteint, exécution du callback quand même');
                callback();
            });
    }

    /**
     * Vérifier si une font spécifique est chargée
     * @param {string} fontFamily - Nom de la font
     * @param {string} size - Taille pour le test (défaut: '16px')
     * @returns {boolean}
     */
    static isLoaded(fontFamily, size = '16px') {
        if (!document.fonts) return true; // On assume que c'est bon
        return document.fonts.check(`${size} "${fontFamily}"`);
    }

    /**
     * Obtenir le statut de chargement de plusieurs fonts
     * @param {string[]} fontFamilies - Liste des fonts à vérifier
     * @returns {Object} Objet avec le statut de chaque font
     */
    static getLoadingStatus(fontFamilies) {
        const status = {};
        fontFamilies.forEach(family => {
            status[family] = this.isLoaded(family);
        });
        return status;
    }

    /**
     * Callback quand le navigateur est idle ET que les fonts sont prêtes
     * Parfait pour wire:navigate et autres SPAs
     * @param {Function} callback - Fonction à exécuter
     * @param {Object} options - Options (fonts, timeout, etc.)
     */
    static onIdleReady(callback, options = {}) {
        const initWhenIdle = () => {
            this.onReady(() => {
                // Forcer un reflow pour s'assurer que les fonts sont appliquées au layout
                document.body.offsetHeight;
                callback();
            }, options);
        };

        if ('requestIdleCallback' in window) {
            requestIdleCallback(initWhenIdle);
        } else {
            setTimeout(initWhenIdle, 16);
        }
    }
}

// Export pour utilisation
export default FontLoader;
