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
    public const SHARE_BY_FACEBOOK = 'facebook';
    public const SHARE_BY_INSTAGRAM = 'instagram';
    public const SHARE_BY_LINKEDIN = 'linkedin';
    public const SHARE_BY_X = 'x';
    private const ALL_SHARE_OPTIONS = [
        self::SHARE_COPY_LINK,
        self::SHARE_BY_EMAIL,
        self::SHARE_BY_SMS,
        self::SHARE_BY_WHATSAPP,
        self::SHARE_BY_MESSENGER,
        self::SHARE_BY_CHATGPT,
        self::SHARE_BY_CLAUDE,
        self::SHARE_BY_PERPLEXITY,
        self::SHARE_BY_FACEBOOK,
        self::SHARE_BY_INSTAGRAM,
        self::SHARE_BY_LINKEDIN,
        self::SHARE_BY_X,
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

    /**
     * Language suffix used to scope the share options cache key.
     *
     * The share toggles live on a Polylang-aware ACF options page
     * (`options_{locale}_share_*`), so their values differ per language.
     * A single global key serves one language's toggles to every other.
     *
     * Mirrors the locale resolution of the ACF↔Polylang options bridge:
     * outside REST the current Polylang locale, otherwise the WordPress locale.
     */
    private static function getCacheLanguageSuffix(): string
    {
        if (!defined('REST_API') && function_exists('pll_current_language')) {
            $locale = pll_current_language('locale');

            if (is_string($locale) && '' !== $locale) {
                return $locale;
            }
        }

        return get_locale();
    }

    /**
     * Locale of the site's default language, which supplies the toggles for
     * any language whose options page has never been saved.
     */
    private static function getDefaultLocale(): string
    {
        if (function_exists('pll_default_language')) {
            $locale = pll_default_language('locale');

            if (is_string($locale) && '' !== $locale) {
                return $locale;
            }
        }

        return get_locale();
    }

    /**
     * Reads a language's stored toggles straight from their option rows.
     *
     * ACF cannot read another language's options page on the front end —
     * get_field(FIELD_SHARE, 'options_xx_XX') returns null there — so the
     * rows are read under the very convention the ACF↔Polylang bridge
     * writes them with: options_{locale}_{group}_{subfield}.
     *
     * A language that was never saved has no row at all, which is what
     * separates "not configured" from "deliberately all disabled".
     *
     * @return array<string, bool>
     */
    private static function readStoredOptions(string $locale): array
    {
        $options = [];

        foreach (ShareOptionsAdmin::getShareFieldNames() as $name) {
            $stored = get_option(sprintf('options_%s_%s_%s', $locale, ShareOptionsAdmin::FIELD_SHARE, $name), null);

            if (null !== $stored && false !== $stored) {
                $options[$name] = (bool) $stored;
            }
        }

        return $options;
    }

    public static function getShareOptions(): array
    {
        if (!self::areShareOptionsEnabled()) {
            throw new \Exception('Share feature is disabled. Please enable it in the configuration file.');
        }

        $locale = self::getCacheLanguageSuffix();

        $options = Cache::remember('horizon_share_options_' . $locale, 3600, function () use ($locale) {
            // The language's own toggles win, then the default language's.
            $options = self::readStoredOptions($locale);

            if ([] === $options) {
                $options = self::readStoredOptions(self::getDefaultLocale());
            }

            if ([] !== $options) {
                return $options;
            }

            // Nothing saved in any language: let ACF supply the field
            // defaults. It answers with the group's own raw option row — an
            // empty string — whenever the value cannot be resolved, through
            // the dummy field it builds for an unknown field (see ACF's
            // api-template.php). `?? []` never fired on that string, so it
            // used to reach the array return type below and, worse, get
            // cached for an hour: a single request in an unconfigured
            // language took every language down with it.
            $value = get_field(ShareOptionsAdmin::FIELD_SHARE, 'options');

            return is_array($value) ? $value : null;
        });

        return $options ?? [];
    }

    public static function hasAtLeastOneShareOption(): bool
    {
        return in_array(true, self::getShareOptions());
    }
}