<?php

declare(strict_types=1);

namespace Adeliom\HorizonTools\Services;

use Illuminate\Support\Facades\Config;

/**
 * Expéditeur commun à tous les e-mails du site, défini une fois au lieu d'être répété dans chaque
 * notification Gravity Forms.
 *
 * La fonctionnalité est désactivée par défaut : `gravityforms.sender.enabled` doit être passé à true
 * dans le `config/gravityforms.php` du thème. Sans ce garde-fou, une simple mise à jour du paquet
 * changerait l'expéditeur des projets qui s'appuient sur des adresses définies par notification.
 *
 * Le réglage se saisit dans Formulaires > Réglages > E-mails, et non dans une page d'options ACF,
 * pour que la fonctionnalité ne dépende ni d'ACF ni de la structure du back-office du thème. Un
 * thème qui préfère sa propre interface passe par le filtre self::FILTER.
 */
class MailSenderService
{
    /** Option unique où l'écran de réglages Gravity Forms enregistre les deux valeurs. */
    public const OPTION_NAME = 'horizon_tools_mail_sender';

    public const SETTING_ADDRESS = 'senderAddress';
    public const SETTING_NAME = 'senderName';

    /**
     * Filtre du couple adresse / nom résolu, appliqué après lecture de l'option.
     *
     * Reçoit et doit retourner un tableau `['address' => string, 'name' => string]`. Une adresse
     * invalide est ignorée. C'est le point d'entrée d'un thème qui veut piloter l'expéditeur depuis
     * sa propre page d'options plutôt que depuis l'écran Gravity Forms.
     */
    public const FILTER = 'horizon_tools_mail_sender';

    /** @var array{address: string, name: string}|null */
    private static ?array $resolved = null;

    public static function isEnabled(): bool
    {
        return (bool) (Config::get('gravityforms.sender.enabled') ?? false);
    }

    /**
     * Les e-mails hors Gravity Forms (réinitialisation de mot de passe, alertes WordPress) suivent
     * le même expéditeur, sauf si le projet préfère limiter la fonctionnalité aux formulaires.
     */
    public static function appliesToWordPressMails(): bool
    {
        return (bool) (Config::get('gravityforms.sender.applyToWordPressMails') ?? true);
    }

    public static function getAddress(): string
    {
        return self::resolve()['address'];
    }

    public static function getName(): string
    {
        return self::resolve()['name'];
    }

    /**
     * À appeler après toute écriture de l'option, sans quoi la même requête continuerait de servir
     * la valeur précédente — l'écran de réglages afficherait l'ancien expéditeur juste après
     * l'enregistrement.
     */
    public static function flushCache(): void
    {
        self::$resolved = null;
    }

    /** @return array{address: string, name: string} */
    private static function resolve(): array
    {
        if (null !== self::$resolved) {
            return self::$resolved;
        }

        $stored = get_option(self::OPTION_NAME, []);
        $stored = is_array($stored) ? $stored : [];

        $sender = apply_filters(self::FILTER, [
            'address' => trim((string) ($stored[self::SETTING_ADDRESS] ?? '')),
            'name' => trim((string) ($stored[self::SETTING_NAME] ?? '')),
        ]);

        $sender = is_array($sender) ? $sender : [];
        $address = trim((string) ($sender['address'] ?? ''));

        return self::$resolved = [
            // Une adresse invalide ferait échouer l'envoi : mieux vaut ignorer le réglage que
            // casser toutes les notifications du site.
            'address' => is_email($address) ? $address : '',
            'name' => trim((string) ($sender['name'] ?? '')),
        ];
    }
}
