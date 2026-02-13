<?php

declare(strict_types=1);

namespace Adeliom\HorizonTools\Services;

use Adeliom\HorizonTools\Admin\ShareOptionsAdmin;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;

class ShareService
{
    public static function getShareOptions(): array
    {
        if (!Config::get('share.enable', false)) {
            throw new \Exception('Share feature is disabled. Please enable it in the configuration file.');
        }

        return Cache::remember('horizon_share_options', 3600, function () {
            return get_field(ShareOptionsAdmin::FIELD_SHARE, 'options') ?? [];
        });
    }

    public static function hasAtLeastOneShareOption(): bool
    {
        return in_array(true, self::getShareOptions());
    }
}
