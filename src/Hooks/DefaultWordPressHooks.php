<?php

declare(strict_types=1);

namespace Adeliom\HorizonTools\Hooks;

use Adeliom\HorizonTools\Services\ColorService;
use Adeliom\HorizonTools\Services\ImageService;
use enshrined\svgSanitize\Sanitizer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Request;
use League\ColorExtractor\Color;
use League\ColorExtractor\ColorExtractor;
use League\ColorExtractor\Palette;

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
        ];

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

    public function handleFooterText(string $text): string
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
        if (function_exists('get_site_icon_url')) {
            $iconUrl = null;

            if ($configLogoUrl = Config::get('back-office.login.header.logo.url')) {
                $iconUrl = $configLogoUrl;
            } elseif ($faviconUrl = get_site_icon_url()) {
                $iconUrl = $faviconUrl;
            }

            if (null !== $iconUrl) {
                $height = min(Config::get('back-office.login.header.logo.height', 120) ?? 120, 320);
                $width = min(Config::get('back-office.login.header.logo.width', 120) ?? 120, 320);
                $radius = Config::get('back-office.login.header.logo.radius', 4) ?? 4;
                $backgroundColor = Config::get('back-office.login.header.logo.backgroundColor', '#FFFFFF') ?? '#FFFFFF';
                $useMainColor = Config::get('back-office.login.useMainColor', false);

                echo <<<EOF
<style>
#login h1, .login h1 {
    height: {$height}px;
    width: {$width}px;
    margin: 0 auto 16px;
    background-color: $backgroundColor;
    border-radius: {$radius}px;
}
#login h1 a, .login h1 a {
    background-image: url("$iconUrl");
    background-size: contain;
    height: {$height}px;
    width: {$width}px;
    border-radius: {$radius}px;
    position: relative;
}
</style>
EOF;

                if ($useMainColor) {
                    $mainColor = ImageService::getMainColorFromImageByUrl($iconUrl);
                    $boxShadow = 'inset 0 1px 1px rgba(0, 0, 0, 0.075)';

                    if (null !== $mainColor) {
                        $mainColorLight = ColorService::adjustBrightness($mainColor, 0.9);
                        $mainColorDark = ColorService::adjustBrightness($mainColor, -0.25);

                        echo <<<EOF
<style>
#login .button, #login .button-secondary {
    color: $mainColor;
}
#login .button:focus, #login .button-secondary:focus {
	border-color: $mainColor;
	box-shadow: $boxShadow;
}
#login #backtoblog a:hover {
    color: $mainColor;
}
#login #nav a:hover {
    color: $mainColor;
}
#login .message, #login .notice, #login .sucess {
    border-left-color: $mainColor;
}
.login .language-switcher .button {
    color: $mainColor;
    border-color: $mainColor;
}
.login .language-switcher .button:hover {
    color: $mainColorDark;
    border-color: $mainColorDark;
    background: $mainColorLight;
}
.login .language-switcher select:focus {
    border-color: $mainColor;
    color:$mainColor;
    box-shadow: $boxShadow;
}
.login .language-switcher select:hover {
    color:$mainColor;
}
#login .button-primary {
    background: $mainColor;
    border-color: $mainColor;
    color: #FFFFFF;
}
#login input[type=text]:focus, #login input[type=password]:focus {
	border-color: $mainColor;
	box-shadow: $boxShadow;
}
#login a {
	color: $mainColor;
}
#login a:hover {
    color: $mainColorDark;
}
</style>
EOF;
                    }
                }
            }
        }
    }
}
