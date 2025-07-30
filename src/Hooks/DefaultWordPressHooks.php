<?php

declare(strict_types=1);

namespace Adeliom\HorizonTools\Hooks;

use Adeliom\HorizonTools\Admin\SearchEngineOptionsAdmin;
use Adeliom\HorizonTools\Services\BackOfficeService;
use Adeliom\HorizonTools\Services\ColorService;
use Adeliom\HorizonTools\Services\SearchEngineService;
use enshrined\svgSanitize\Sanitizer;
use Extended\ACF\Key;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Request;

class DefaultWordPressHooks extends AbstractHook
{
    private const CLEAR_CACHE = 'clear-cache';
    private const CLEAR_CACHE_ROLES = ['administrator'];

    public function init(): void
    {
        $filters = [
            ['upload_mimes', [$this, 'allowedMimeTypes']],
            ['wp_handle_upload_prefilter', [$this, 'cleanMedias']],
            ['image_downsize', [$this, 'svgAttributes'], 10, 2],
            ['admin_footer_text', [$this, 'handleFooterText'], 10, 1],
            ['login_headerurl', [$this, 'handleLoginHeaderUrl'], 10, 1],
            ['login_headertitle', [$this, 'handleLoginHeaderTitle'], 10, 1],
            ['login_enqueue_scripts', [$this, 'handleLoginHeaderImage'], 10, 1],
            ['admin_enqueue_scripts', [$this, 'handleAdminStyles'], 10, 1],
            ['admin_init', [$this, 'disabledCustomThemes'], 10, 0],
        ];

        if (SearchEngineService::isSearchEngineEnabled()) {
            $filters[] = [
                sprintf('acf/update_value/name=%s', SearchEngineOptionsAdmin::FIELD_HORIZON_SEARCH),
                [$this, 'handleSearchEngineConfigUpdate'],
                10,
                4,
            ];

            $excludedPostsFieldKey = sprintf(
                'field_%s',
                Key::hash(
                    strtolower(
                        sprintf(
                            '%s_%s_%s',
                            sanitize_title(SearchEngineOptionsAdmin::$title),
                            SearchEngineOptionsAdmin::FIELD_HORIZON_SEARCH,
                            SearchEngineOptionsAdmin::FIELD_EXCLUDED_POSTS
                        )
                    )
                )
            );

            $filters[] = [
                sprintf('acf/fields/relationship/query/key=%s', $excludedPostsFieldKey),
                [$this, 'handleSearchEngineExcludedPostsQuery'],
                10,
                3,
            ];
        }

        foreach ($filters as $filter) {
            add_filter(...$filter);
        }

        add_action('admin_bar_menu', [$this, 'addClearCacheButton'], 50);
        add_action('init', [$this, 'handleClearCache']);
    }

    public function allowedMimeTypes($mimes): array
    {
        if (is_array($mimes)) {
            if (Config::get('medias.allow.svg')) {
                $mimes['svg'] = 'image/svg+xml';
            }
        }

        return $mimes;
    }

    public function cleanMedias($file): array
    {
        switch ($file['type']) {
            case 'image/svg+xml':
                if (Config::get('medias.sanitize.svg')) {
                    $sanitizer = new Sanitizer();
                    $cleanSvg = $sanitizer->sanitize(file_get_contents($file['tmp_name']));

                    if ($cleanSvg) {
                        file_put_contents($file['tmp_name'], $cleanSvg);
                    } else {
                        $file['error'] = 'Failed to sanitize SVG';
                    }
                }
                break;
            default:
                break;
        }

        return $file;
    }

    public function svgAttributes($out, $id)
    {
        $image_url = wp_get_attachment_url($id);

        if (!$image_url) {
            return false;
        }

        $file_ext = pathinfo($image_url, PATHINFO_EXTENSION);

        if (is_admin() || 'svg' !== $file_ext) {
            return false;
        }

        return [$image_url, null, null, false];
    }

    public function addClearCacheButton(\WP_Admin_Bar $wpAdminBar): void
    {
        if ($currentUser = get_user(get_current_user_id())) {
            if ($currentUser instanceof \WP_User) {
                foreach (self::CLEAR_CACHE_ROLES as $CACHE_ROLE) {
                    if (in_array($CACHE_ROLE, $currentUser->roles)) {
                        $wpAdminBar->add_menu([
                            'id' => 'clear-cache',
                            'title' => 'Vider le cache serveur',
                            'href' => sprintf('%s/?%s=1', Request::url(), self::CLEAR_CACHE),
                        ]);

                        break;
                    }
                }
            }
        }
    }

    public function handleClearCache(): void
    {
        if (Request::get(self::CLEAR_CACHE)) {
            if ($currentUser = get_user(get_current_user_id())) {
                if ($currentUser instanceof \WP_User) {
                    foreach (self::CLEAR_CACHE_ROLES as $CACHE_ROLE) {
                        if (in_array($CACHE_ROLE, $currentUser->roles)) {
                            Cache::clear();

                            break;
                        }
                    }
                }
            }
        }
    }

    public function handleFooterText(?string $text): string
    {
        $manualText = Config::get('back-office.bo.footerText');

        if (empty($manualText)) {
            $defaultTranslatableContent = __('Développé avec soin par l’agence digitale');

            $text = <<<EOF
<span id="footer-thankyou">
$defaultTranslatableContent <a href="https://adeliom.com/">Adeliom</a>
</span>
EOF;
        } else {
            $text = $manualText;
        }

        return $text;
    }

    public function handleLoginHeaderUrl(string $url): string
    {
        $manualUrl = Config::get('back-office.login.header.url');

        if (empty($manualUrl)) {
            if (function_exists('home_url')) {
                $url = home_url();
            }
        } else {
            $url = $manualUrl;
        }

        return $url;
    }

    public function handleLoginHeaderTitle(string $title): string
    {
        $manualTitle = Config::get('back-office.login.header.title');

        if (empty($manualTitle)) {
            if (function_exists('get_bloginfo')) {
                if ($name = get_bloginfo('name')) {
                    $title = !empty($name) ? $name : $title;
                }
            }
        } else {
            $title = $manualTitle;
        }

        return $title;
    }

    public function handleLoginHeaderImage(): void
    {
        $height = min(Config::get('back-office.login.header.logo.height', 120) ?? 120, 320);
        $width = min(Config::get('back-office.login.header.logo.width', 120) ?? 120, 320);
        $radius = Config::get('back-office.login.header.logo.radius', 4) ?? 4;
        $backgroundColor = Config::get('back-office.login.header.logo.backgroundColor', '#FFFFFF') ?? '#FFFFFF';
        $useMainColor = self::useMainColor();
        $iconUrl = BackOfficeService::getBackOfficeIconUrl();

        $loginHeaderStylePath = sprintf('%s/../../resources/styles/back-office/login/header.css', __DIR__);
        $loginHeaderStyle = file_exists($loginHeaderStylePath) ? file_get_contents($loginHeaderStylePath) : null;

        $styleVars = [];
        $styleContent = '';

        $styleVars['horizon-login-header-height'] = sprintf('%dpx', $height);
        $styleVars['horizon-login-header-width'] = sprintf('%dpx', $width);
        $styleVars['horizon-login-header-border-radius'] = sprintf('%dpx', $radius);
        $styleVars['horizon-login-header-background-color'] = $backgroundColor;

        $styleContent .= $loginHeaderStyle;

        if (null !== $iconUrl) {
            $styleContent .= <<<EOF
#login h1 a, .login h1 a {
	background-image: url("$iconUrl");
}
EOF;
        }

        if ($useMainColor && ($mainColor = self::getMainColor())) {
            if (null !== $mainColor) {
                $boxShadow = 'inset 0 1px 1px rgba(0, 0, 0, 0.075)';
                $mainColorLight = self::getMainColorLight(mainColor: $mainColor);
                $mainColorDark = self::getMainColorDark(mainColor: $mainColor);

                $styleVars['horizon-admin-main-color'] = $mainColor;
                $styleVars['horizon-admin-main-color-light'] = $mainColorLight;
                $styleVars['horizon-admin-main-color-dark'] = $mainColorDark;
                $styleVars['horizon-admin-box-shadow'] = $boxShadow;

                $loginStylePath = sprintf('%s/../../resources/styles/back-office/login/login.css', __DIR__);
                $loginStyle = file_exists($loginStylePath) ? file_get_contents($loginStylePath) : null;

                $styleContent .= $loginStyle;
            }
        }

        $vars = '';

        foreach ($styleVars as $styleName => $styleValue) {
            $vars .= sprintf('--%s: %s; ', $styleName, $styleValue);
        }

        $vars = sprintf(':root { %s }', $vars);

        echo sprintf('<style>%s%s</style>', $vars, $styleContent);
    }

    public function handleAdminStyles(): void
    {
        $useMainColor = self::useMainColor();
        $useMainColorTheme = self::useMainColorTheme();

        if ($useMainColor && $useMainColorTheme) {
            $styleVars = [];
            $styleContent = '';

            $adminStylePath = sprintf('%s/../../resources/styles/back-office/global.css', __DIR__);
            $adminStyle = file_exists($adminStylePath) ? file_get_contents($adminStylePath) : null;

            $adminScriptPath = sprintf('%s/../../resources/scripts/back-office/global.js', __DIR__);
            $adminScript = file_exists($adminScriptPath) ? file_get_contents($adminScriptPath) : null;

            if (null !== $adminScript) {
                echo sprintf('<script>%s</script>', $adminScript);
            }

            if ($mainColor = self::getMainColor()) {
                if (null !== $mainColor) {
                    $boxShadow = 'inset 0 1px 1px rgba(0, 0, 0, 0.075)';
                    $mainColorLight = self::getMainColorLight(mainColor: $mainColor);
                    $mainColorDark = self::getMainColorDark(mainColor: $mainColor);

                    $styleVars['horizon-admin-main-color'] = $mainColor;
                    $styleVars['horizon-admin-main-color-light'] = $mainColorLight;
                    $styleVars['horizon-admin-main-color-dark'] = $mainColorDark;
                    $styleVars['horizon-admin-box-shadow'] = $boxShadow;

                    $styleContent .= $adminStyle;
                }
            }

            $vars = '';

            foreach ($styleVars as $styleName => $styleValue) {
                $vars .= sprintf('--%s: %s; ', $styleName, $styleValue);
            }

            $vars = sprintf(':root { %s }', $vars);

            echo sprintf('<style id="test">%s%s</style>', $vars, $styleContent);
        }
    }

    public static function disabledCustomThemes()
    {
        global $_wp_admin_css_colors;

        if (self::useMainColor() && self::useMainColorTheme()) {
            $allowed = ['fresh'];

            foreach ($_wp_admin_css_colors as $key => $value) {
                if (!in_array($key, $allowed)) {
                    unset($_wp_admin_css_colors[$key]);
                }
            }
        }
    }

    private static function getMainColor(): ?string
    {
        return ColorService::getSiteMainColorFromIcon();
    }

    private static function getMainColorLight(?string $mainColor = null): ?string
    {
        if (null === $mainColor) {
            $mainColor = self::getMainColor();
        }

        return $mainColor ? ColorService::adjustBrightness($mainColor, 0.75) : null;
    }

    private static function getMainColorDark(?string $mainColor = null): ?string
    {
        if (null === $mainColor) {
            $mainColor = self::getMainColor();
        }

        return $mainColor ? ColorService::adjustBrightness($mainColor, -0.25) : null;
    }

    private static function useMainColor(): bool
    {
        return Config::get('back-office.login.useMainColor', false);
    }

    private static function useMainColorTheme(): bool
    {
        return Config::get('back-office.login.useMainColorTheme', false);
    }

    public function handleSearchEngineConfigUpdate(mixed $value, int|string $postId, array $field, mixed $original)
    {
        Cache::forget(SearchEngineService::HORIZON_SEARCH_ENGINE_CONFIG_CACHE_KEY);

        return $value;
    }

    public function handleSearchEngineExcludedPostsQuery(array $args, array $field, string|int $postId): array
    {
        if ($searchResultsPage = SearchEngineService::getSearchEngineResultsPage()) {
            if ($searchResultsPage instanceof \WP_Post) {
                $args['post__not_in'] = [$searchResultsPage->ID];
            }
        }

        return $args;
    }
}