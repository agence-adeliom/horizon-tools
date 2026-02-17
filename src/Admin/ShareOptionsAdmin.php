<?php

declare(strict_types=1);

namespace Adeliom\HorizonTools\Admin;

use Adeliom\HorizonTools\Services\ShareService;
use Extended\ACF\Fields\Group;
use Extended\ACF\Fields\Tab;
use Extended\ACF\Fields\TrueFalse;

class ShareOptionsAdmin extends AbstractAdmin
{
    public static ?string $title = 'Partage';
    public static ?string $slug = 'share-options';
    public static bool $isOptionPage = true;
    public static ?string $optionPageIcon = 'dashicons-share';

    public const FIELD_SHARE = 'share';
    public const FIELD_SHARE_ENABLE_COPY_LINK = 'enableCopyLink';
    public const FIELD_SHARE_ENABLE_EMAIL = 'enableEmail';
    public const FIELD_SHARE_ENABLE_SMS = 'enableSMS';
    public const FIELD_SHARE_ENABLE_WHATSAPP = 'enableWhatsapp';
    public const FIELD_SHARE_ENABLE_MESSENGER = 'enableMessenger';
    public const FIELD_SHARE_ENABLE_CHATGPT = 'enableChatGPT';
    public const FIELD_SHARE_ENABLE_CLAUDE = 'enableClaude';
    public const FIELD_SHARE_ENABLE_PERPLEXITY = 'enablePerplexity';

    private const SHARE_FIELD_MAP = [
        ShareService::SHARE_COPY_LINK => ['label' => 'Activer la copie du lien', 'name' => self::FIELD_SHARE_ENABLE_COPY_LINK],
        ShareService::SHARE_BY_EMAIL => ['label' => 'Activer le partage par e-mail', 'name' => self::FIELD_SHARE_ENABLE_EMAIL],
        ShareService::SHARE_BY_SMS => ['label' => 'Activer le partage par SMS', 'name' => self::FIELD_SHARE_ENABLE_SMS],
        ShareService::SHARE_BY_WHATSAPP => ['label' => 'Activer le partage par WhatsApp', 'name' => self::FIELD_SHARE_ENABLE_WHATSAPP],
        ShareService::SHARE_BY_MESSENGER => ['label' => 'Activer le partage par Messenger', 'name' => self::FIELD_SHARE_ENABLE_MESSENGER],
        ShareService::SHARE_BY_CHATGPT => ['label' => 'Activer le partage par ChatGPT', 'name' => self::FIELD_SHARE_ENABLE_CHATGPT],
        ShareService::SHARE_BY_CLAUDE => ['label' => 'Activer le partage par Claude', 'name' => self::FIELD_SHARE_ENABLE_CLAUDE],
        ShareService::SHARE_BY_PERPLEXITY => ['label' => 'Activer le partage par Perplexity', 'name' => self::FIELD_SHARE_ENABLE_PERPLEXITY],
    ];

    private function getShareServiceToggles(): array
    {
        $toggles = [];

        foreach (ShareService::getEnabledShareServices() as $service) {
            if (isset(self::SHARE_FIELD_MAP[$service])) {
                $config = self::SHARE_FIELD_MAP[$service];
                $toggles[] = TrueFalse::make(__($config['label']), $config['name'])->default(false)->stylized();
            }
        }

        return $toggles;
    }

    public function getFields(): ?iterable
    {
        yield Tab::make(__('Partage'), 'share_tab')->placement('left');
        yield Group::make(__('Paramètres de partage'), self::FIELD_SHARE)->fields($this->getShareServiceToggles());
    }

    public function getOptionPageParent(): ?string
    {
        return null;
    }

    public function getLocation(): iterable
    {
        yield from parent::getLocation();
    }
}
