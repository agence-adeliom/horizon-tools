<?php

declare(strict_types=1);

namespace Adeliom\HorizonTools\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;

class BackOfficeService
{
    public static function getBackOfficeIconUrl(bool $useCache = true): ?string
    {
        if ($useCache) {
            return Cache::remember('back_office_icon_url', 3600, function () {
                return self::getBackOfficeIconUrlLogic();
            });
        }

        return self::getBackOfficeIconUrlLogic();
    }

    private static function getBackOfficeIconUrlLogic(): ?string
    {
        $iconUrl = null;

        if ($configLogoUrl = Config::get('back-office.login.header.logo.url')) {
            $iconUrl = $configLogoUrl;
        }

        if (function_exists('get_site_icon_url')) {
            if (null === $iconUrl && ($faviconUrl = get_site_icon_url())) {
                $iconUrl = $faviconUrl;
            }
        }

        return $iconUrl;
    }
}
