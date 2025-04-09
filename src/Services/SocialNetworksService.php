<?php

declare(strict_types=1);

namespace Adeliom\HorizonTools\Services;

use Adeliom\HorizonTools\Services\SeoService;

class SocialNetworksService
{
    public const SHARE_URL_PLACEHOLDER = '%URL%';
    public const SHARE_TITLE_PLACEHOLDER = '%TITLE%';
    private const DATA_KEYS_TO_HANDLE = ['subject', 'body'];

    private const TYPE_LINK = 'link';
    private const TYPE_EMAIL = 'email';

    public static ?string $currentUrl = null;
    public static ?string $currentTitle = null;

    /**
     * Récupère la configuration des réseaux sociaux.
     *
     * @param bool $withFacebook Inclure Facebook dans la configuration.
     * @param bool $withTwitter Inclure Twitter dans la configuration.
     * @param bool $withLinkedIn Inclure LinkedIn dans la configuration.
     * @param bool $withEmail Inclure l'email dans la configuration.
     * @return array<string, array{icon: string, name: string, shareUrl: string, shareCurrentUrl?: string}>
     */
    public static function getConfig(
        bool $withFacebook = true,
        bool $withTwitter = true,
        bool $withLinkedIn = true,
        bool $withEmail = true
    ): array {
        $titlePlaceholder = self::SHARE_TITLE_PLACEHOLDER;
        $urlPlaceholder = self::SHARE_URL_PLACEHOLDER;

        $siteName = env('SITE_NAME') ?? null;

        if (null === $siteName) {
            throw new \Exception('SITE_NAME is not set in the .env file');
        }

        self::$currentUrl = SeoService::getCurrentUrl() ?? null;
        self::$currentTitle = SeoService::getCurrentTitle() ?? null;

        $networks = array_filter([
            'facebook' => $withFacebook
                ? [
                    'icon' => 'fab-facebook-f',
                    'type' => self::TYPE_LINK,
                    'name' => 'Facebook',
                    'shareUrl' => sprintf('https://www.facebook.com/sharer/sharer.php?u=%s', self::SHARE_URL_PLACEHOLDER),
                ]
                : null,
            'twitter' => $withTwitter
                ? [
                    'icon' => 'fab-x-twitter',
                    'type' => self::TYPE_LINK,
                    'name' => 'Twitter / X',
                    'shareUrl' => sprintf('https://twitter.com/intent/tweet?url=%s', self::SHARE_URL_PLACEHOLDER),
                ]
                : null,
            'linkedin' => $withLinkedIn
                ? [
                    'icon' => 'fab-linkedin',
                    'type' => self::TYPE_LINK,
                    'name' => 'LinkedIn',
                    'shareUrl' => sprintf('https://www.linkedin.com/sharing/share-offsite/?url=%s', self::SHARE_URL_PLACEHOLDER),
                ]
                : null,
            'email' => $withEmail
                ? [
                    'icon' => 'far-envelope',
                    'type' => self::TYPE_EMAIL,
                    'name' => 'E-mail',
                    'data' => [
                        'subject' => sprintf('%s - %s', $siteName, $titlePlaceholder),
                        'body' => <<<EOF
Cette page publiée sur le site $siteName pourrait vous intéresser : $titlePlaceholder. Voici le lien : $urlPlaceholder
EOF
                    ,
                    ],
                ]
                : null,
        ]);

        foreach ($networks as $name => $config) {
            if (
                self::$currentUrl &&
                isset($config['shareUrl']) &&
                is_string($config['shareUrl']) &&
                str_contains($config['shareUrl'], self::SHARE_URL_PLACEHOLDER)
            ) {
                $networks[$name]['shareCurrentUrl'] = self::handleUrl($networks[$name]['shareUrl']);
            }

            if (isset($networks[$name]['data'])) {
                $networks[$name] = self::handleData($networks[$name]);
            }

            $networks[$name] = self::handleType($networks[$name]);
        }

        return $networks;
    }

    private static function handleUrl(?string $source): ?string
    {
        if ($source) {
            $source = str_replace(self::SHARE_URL_PLACEHOLDER, self::$currentUrl, $source);
        }

        return $source;
    }

    private static function handleData(array $source): array
    {
        foreach (self::DATA_KEYS_TO_HANDLE as $key) {
            if (isset($source['data'][$key]) && is_string($source['data'][$key])) {
                if (str_contains($source['data'][$key], self::SHARE_TITLE_PLACEHOLDER) && self::$currentTitle) {
                    $source['data'][$key] = str_replace(self::SHARE_TITLE_PLACEHOLDER, self::$currentTitle, $source['data'][$key]);
                }

                if (str_contains($source['data'][$key], self::SHARE_URL_PLACEHOLDER) && self::$currentUrl) {
                    $source['data'][$key] = str_replace(self::SHARE_URL_PLACEHOLDER, self::$currentUrl, $source['data'][$key]);
                }
            }
        }

        return $source;
    }

    private static function handleType(array $source): array
    {
        switch ($source['type'] ?? null) {
            case self::TYPE_EMAIL:
                $source['shareCurrentUrl'] = sprintf(
                    'mailto:?subject=%s&body=%s',
                    $source['data']['subject'] ?? '',
                    $source['data']['body'] ?? ''
                );
                break;
            default:
                break;
        }

        return $source;
    }
}
