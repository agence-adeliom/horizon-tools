<?php

declare(strict_types=1);

namespace Adeliom\HorizonTools\Services;

use Adeliom\HorizonTools\Admin\ShareOptionsAdmin;
use Illuminate\Support\Facades\Cache;

class ShareService
{
    public static function getShareOptions(): array
    {
        return Cache::remember('share_options', 3600, function () {
            return get_field(ShareOptionsAdmin::FIELD_SHARE, 'options') ?? [];
        });
    }
}
