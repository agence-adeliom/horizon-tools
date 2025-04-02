<?php

declare(strict_types=1);

namespace Adeliom\HorizonTools\Hooks;

use enshrined\svgSanitize\Sanitizer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Request;

class DefaultWordPressHooks extends AbstractHook
{
    private const CLEAR_CACHE = 'clear-cache';

    public function init(): void
    {
        $filters = [
            ['upload_mimes', [$this, 'allowedMimeTypes']],
            ['wp_handle_upload_prefilter', [$this, 'cleanMedias']],
            ['image_downsize', [$this, 'svgAttributes'], 10, 2],
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
        $wpAdminBar->add_menu([
            'id' => 'clear-cache',
            'title' => 'Vider le cache serveur',
            'href' => sprintf('%s/?%s=1', Request::url(), self::CLEAR_CACHE),
        ]);
    }

    public function handleClearCache(): void
    {
        if (Request::get(self::CLEAR_CACHE)) {
            if ($currentUser = get_user(get_current_user_id())) {
                if ($currentUser instanceof \WP_User) {
                    if (in_array('administrator', $currentUser->roles)) {
                        Cache::clear();
                    }
                }
            }
        }
    }
}
