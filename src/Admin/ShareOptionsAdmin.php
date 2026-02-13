<?php

declare(strict_types=1);

namespace Adeliom\HorizonTools\Admin;

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

    public function getFields(): ?iterable
    {
        yield Tab::make(__('Partage'), 'share_tab')->placement('left');
        yield Group::make(__('Paramètres de partage'), self::FIELD_SHARE)->fields([
            TrueFalse::make(__('Activer la copie du lien'), self::FIELD_SHARE_ENABLE_COPY_LINK)
                ->default(false)
                ->stylized(),
            TrueFalse::make(__('Activer le partage par e-mail'), self::FIELD_SHARE_ENABLE_EMAIL)
                ->default(false)
                ->stylized(),
            TrueFalse::make(__('Activer le partage par SMS'), self::FIELD_SHARE_ENABLE_SMS)
                ->default(false)
                ->stylized(),
            TrueFalse::make(__('Activer le partage par WhatsApp'), self::FIELD_SHARE_ENABLE_WHATSAPP)
                ->default(false)
                ->stylized(),
            TrueFalse::make(__('Activer le partage par Messenger'), self::FIELD_SHARE_ENABLE_MESSENGER)
                ->default(false)
                ->stylized(),
            TrueFalse::make(__('Activer le partage par ChatGPT'), self::FIELD_SHARE_ENABLE_CHATGPT)
                ->default(false)
                ->stylized(),
            TrueFalse::make(__('Activer le partage par Claude'), self::FIELD_SHARE_ENABLE_CLAUDE)
                ->default(false)
                ->stylized(),
            TrueFalse::make(__('Activer le partage par Perplexity'), self::FIELD_SHARE_ENABLE_PERPLEXITY)
                ->default(false)
                ->stylized(),
        ]);
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
