<?php

declare(strict_types=1);

namespace Adeliom\HorizonTools\Services;

use Adeliom\HorizonTools\Admin\ShareOptionsAdmin;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;

class ShareService
{
    public const SHARE_COPY_LINK = 'copyLink';
    public const SHARE_BY_EMAIL = 'email';
    public const SHARE_BY_SMS = 'sms';
    public const SHARE_BY_WHATSAPP = 'whatsapp';
    public const SHARE_BY_MESSENGER = 'messenger';
    public const SHARE_BY_CHATGPT = 'chatGPT';
    public const SHARE_BY_CLAUDE = 'claude';
    public const SHARE_BY_PERPLEXITY = 'perplexity';
    private const ALL_SHARE_OPTIONS = [
        self::SHARE_COPY_LINK,
        self::SHARE_BY_EMAIL,
        self::SHARE_BY_SMS,
        self::SHARE_BY_WHATSAPP,
        self::SHARE_BY_MESSENGER,
        self::SHARE_BY_CHATGPT,
        self::SHARE_BY_CLAUDE,
        self::SHARE_BY_PERPLEXITY,
    ];

    public static function areShareOptionsEnabled(): bool
    {
        return Config::get('share.enable', false);
    }

    /**
     * @return array<string>
     */
    public static function getEnabledShareServices(): array
    {
        if (!self::areShareOptionsEnabled()) {
            return [];
        }

        $options = Config::get('share.services', []);

        if (empty($options)) {
            $options = self::ALL_SHARE_OPTIONS;
        }

        return $options;
    }

    public static function getShareOptions(): array
    {
        if (!self::areShareOptionsEnabled()) {
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
