<?php

declare(strict_types=1);

namespace Adeliom\HorizonTools\Hooks;

use Adeliom\HorizonTools\Services\MailSenderService;
use Gravity_Forms\Gravity_Forms\Settings\Settings;

/**
 * Applique l'expéditeur global (MailSenderService) aux notifications Gravity Forms et aux e-mails
 * de WordPress, et porte l'écran de réglages où il se saisit.
 *
 * Le réglage global est un défaut, pas une contrainte : il ne remplit que les champs laissés vides,
 * ou laissés sur la valeur que Gravity Forms y place lui-même. Une adresse saisie dans une
 * notification particulière l'emporte donc toujours, ce qui permet de faire parler un formulaire
 * d'une autre voix sans réglage supplémentaire à inventer.
 *
 * Corollaire assumé de ce choix : `{admin_email}` est indissociable de « non renseigné », puisque
 * c'est le défaut de Gravity Forms (notification.php). Pour qu'une notification suive explicitement
 * l'adresse d'administration du site malgré un réglage global, il faut y écrire l'adresse en clair.
 */
class DefaultMailSenderHooks extends AbstractHook
{
    /** Sous-vue de Formulaires > Réglages qui porte l'écran de saisie. */
    private const SETTINGS_SUBVIEW = 'horizon-mails';

    /** Valeur que Gravity Forms pré-remplit dans « From Email » à la création d'une notification. */
    private const GF_DEFAULT_FROM = '{admin_email}';

    /** Nom d'expéditeur que WordPress utilise faute de mieux (pluggable.php). */
    private const WP_DEFAULT_FROM_NAME = 'WordPress';

    private ?Settings $renderer = null;

    public function init(): void
    {
        if (!MailSenderService::isEnabled()) {
            return;
        }

        /*
            Pas de garde sur l'activation de Gravity Forms : FormService::isGravityFormsActive()
            repose sur is_plugin_active(), absente en contexte WP-CLI, ce qui rendrait les filtres
            invisibles aux scripts de vérification tout en fonctionnant en front. Un add_filter sur
            un hook qui ne se déclenche jamais ne coûte rien.
        */
        add_filter('gform_notification', [$this, 'applySender'], 10, 3);
        add_filter('gform_notification_settings_fields', [$this, 'describeSenderSettings']);
        add_action('admin_init', [$this, 'registerSettingsPage']);

        if (MailSenderService::appliesToWordPressMails()) {
            add_filter('wp_mail_from', [$this, 'mailFrom']);
            add_filter('wp_mail_from_name', [$this, 'mailFromName']);
        }
    }

    /**
     * @param  array<string, mixed>  $notification
     * @param  array<string, mixed>  $form
     * @param  array<string, mixed>|false  $entry
     * @return array<string, mixed>
     */
    public function applySender(array $notification, array $form, $entry): array
    {
        $address = MailSenderService::getAddress();
        $name = MailSenderService::getName();

        if ('' !== $address && $this->isSenderAddressUnset($notification)) {
            $notification['from'] = $address;
        }

        if ('' !== $name && '' === $this->setting($notification, 'fromName')) {
            $notification['fromName'] = $name;
        }

        if ('' === $this->setting($notification, 'replyTo') && $this->isSentToTeam($notification)) {
            $visitorEmail = $this->submittedEmail($form, $entry);

            if (null !== $visitorEmail) {
                $notification['replyTo'] = $visitorEmail;
            }
        }

        return $notification;
    }

    /**
     * Documente le réglage global sous les trois champs concernés de l'écran d'édition d'une
     * notification. Sans ça, la personne qui y lit `{admin_email}` reçoit un e-mail signé autrement
     * sans pouvoir comprendre d'où vient l'écart.
     *
     * Les libellés citent la valeur résolue et non sa provenance : le filtre de MailSenderService
     * permet à un thème de servir ces valeurs depuis sa propre page d'options, auquel cas nommer
     * l'écran Gravity Forms serait faux.
     *
     * @param  array<int, array<string, mixed>>  $sections
     * @return array<int, array<string, mixed>>
     */
    public function describeSenderSettings(array $sections): array
    {
        $address = MailSenderService::getAddress();
        $name = MailSenderService::getName();

        $descriptions = [
            'from' => '' === $address
                ? __("Aucune adresse d'expéditeur globale n'est définie : ce champ fait foi.", 'horizon-tools')
                : sprintf(
                    /* translators: 1: merge tag laissé par défaut, 2: adresse d'expéditeur globale. */
                    __(
                        'Laissé sur <code>%1$s</code>, ce champ suit l\'expéditeur global du site, soit '
                            . '<strong>%2$s</strong>. Saisissez une adresse pour ne changer que cette notification.',
                        'horizon-tools',
                    ),
                    esc_html(self::GF_DEFAULT_FROM),
                    esc_html($address),
                ),
            'fromName' => '' === $name
                ? __(
                    "Aucun nom d'expéditeur global n'est défini : laissé vide, l'e-mail s'affichera sous la "
                        . "seule adresse d'expéditeur.",
                    'horizon-tools',
                )
                : sprintf(
                    /* translators: %s: nom d'expéditeur global. */
                    __("Laissé vide, ce champ suit le nom d'expéditeur global du site, soit <strong>%s</strong>.", 'horizon-tools'),
                    esc_html($name),
                ),
            'replyTo' => __(
                "Laissé vide sur une notification adressée à l'équipe, la réponse est dirigée vers l'adresse "
                    . 'saisie par le visiteur dans le formulaire.',
                'horizon-tools',
            ),
        ];

        foreach ($sections as $sectionIndex => $section) {
            foreach ($section['fields'] ?? [] as $fieldIndex => $field) {
                $fieldName = $field['name'] ?? '';

                // On ne remplace pas une description que Gravity Forms ou une extension aurait déjà posée.
                if (!isset($descriptions[$fieldName]) || isset($field['description'])) {
                    continue;
                }

                $sections[$sectionIndex]['fields'][$fieldIndex]['description'] = $descriptions[$fieldName];
            }
        }

        return $sections;
    }

    /**
     * WordPress applique ce filtre après avoir lu l'en-tête `From` fourni par l'appelant, et sans
     * distinguer les deux cas (pluggable.php). Écraser sans condition annulerait donc l'expéditeur
     * que Gravity Forms vient de calculer, et avec lui toute surcharge par notification : on ne se
     * substitue qu'à l'adresse de repli de WordPress.
     */
    public function mailFrom(string $from): string
    {
        $address = MailSenderService::getAddress();

        if ('' === $address || $from !== $this->wordPressFallbackAddress()) {
            return $from;
        }

        return $address;
    }

    /** Même raisonnement que mailFrom() : on ne remplace que le nom de repli de WordPress. */
    public function mailFromName(string $fromName): string
    {
        $name = MailSenderService::getName();

        if ('' === $name || self::WP_DEFAULT_FROM_NAME !== $fromName) {
            return $fromName;
        }

        return $name;
    }

    /**
     * Déclare l'onglet « E-mails » sous Formulaires > Réglages. L'enregistrement est traité ici, et
     * non au rendu : Gravity Forms appelle le gestionnaire de la sous-vue après avoir écrit l'en-tête
     * de page, trop tard pour un message de confirmation ou une redirection.
     */
    public function registerSettingsPage(): void
    {
        if (!class_exists(\GFForms::class) || !class_exists(Settings::class)) {
            return;
        }

        \GFForms::add_settings_page([
            'name' => self::SETTINGS_SUBVIEW,
            'tab_label' => __('E-mails', 'horizon-tools'),
            'title' => __("Expéditeur des e-mails", 'horizon-tools'),
            'icon' => 'gform-icon--mail',
            'handler' => [$this, 'renderSettingsPage'],
        ]);

        if (self::SETTINGS_SUBVIEW !== rgget('subview')) {
            return;
        }

        $renderer = $this->settingsRenderer();

        if ($renderer->is_save_postback()) {
            $renderer->process_postback();
        }
    }

    public function renderSettingsPage(): void
    {
        wp_enqueue_style('gform_admin');

        $this->settingsRenderer()->render();
    }

    private function settingsRenderer(): Settings
    {
        if (null !== $this->renderer) {
            return $this->renderer;
        }

        return $this->renderer = new Settings([
            'capability' => 'gravityforms_edit_settings',
            'input_name_prefix' => '_horizon_mail_sender',
            // Chaîne et non tableau : le lecteur d'option est déduit du nom, l'écriture reste à
            // notre charge dès qu'un save_callback est fourni (class-settings.php, save_values()).
            'initial_values' => MailSenderService::OPTION_NAME,
            'save_callback' => static function ($values): void {
                update_option(MailSenderService::OPTION_NAME, $values);
                MailSenderService::flushCache();
            },
            'fields' => [
                [
                    'id' => 'horizon-mail-sender',
                    'title' => __("Expéditeur des e-mails", 'horizon-tools'),
                    'description' => __(
                        'Ces deux valeurs servent de défaut à toutes les notifications de formulaire et à tous '
                            . 'les e-mails envoyés par WordPress. Une notification qui définit son propre '
                            . 'expéditeur garde le sien.',
                        'horizon-tools',
                    ),
                    'class' => 'gform-settings-panel--full',
                    'fields' => [
                        [
                            'name' => MailSenderService::SETTING_ADDRESS,
                            'label' => __("Adresse d'expéditeur", 'horizon-tools'),
                            'type' => 'text',
                            'description' => __(
                                'Utilisez une adresse du domaine du site, sans quoi les e-mails risquent '
                                    . "d'être classés en indésirables. Laissée vide, chaque notification garde "
                                    . 'son propre réglage.',
                                'horizon-tools',
                            ),
                            'validation_callback' => static function ($field, $value): void {
                                if (!empty($value) && !is_email($value)) {
                                    $field->set_error(__('Veuillez saisir une adresse e-mail valide.', 'horizon-tools'));
                                }
                            },
                        ],
                        [
                            'name' => MailSenderService::SETTING_NAME,
                            'label' => __("Nom d'expéditeur", 'horizon-tools'),
                            'type' => 'text',
                            'description' => __(
                                "Libellé affiché à la place de l'adresse dans la boîte de réception du destinataire.",
                                'horizon-tools',
                            ),
                        ],
                    ],
                ],
            ],
        ]);
    }

    /** @param array<string, mixed> $notification */
    private function isSenderAddressUnset(array $notification): bool
    {
        $from = $this->setting($notification, 'from');

        return '' === $from || self::GF_DEFAULT_FROM === $from;
    }

    /**
     * Distingue les notifications destinées à l'équipe de celles destinées au visiteur. `toType`
     * vaut `field` quand le destinataire est un champ du formulaire, donc le visiteur lui-même ;
     * `email` et `routing` désignent des adresses fixes, donc l'équipe.
     *
     * @param  array<string, mixed>  $notification
     */
    private function isSentToTeam(array $notification): bool
    {
        return 'field' !== $this->setting($notification, 'toType');
    }

    /**
     * Adresse saisie par le visiteur, prise dans le premier champ e-mail du formulaire. Repérée par
     * type et non par identifiant, pour rester valable sur n'importe quel formulaire.
     *
     * @param  array<string, mixed>  $form
     * @param  array<string, mixed>|false  $entry
     */
    private function submittedEmail(array $form, $entry): ?string
    {
        if (!is_array($entry)) {
            return null;
        }

        foreach ($form['fields'] ?? [] as $field) {
            if (!$field instanceof \GF_Field || 'email' !== $field->get_input_type()) {
                continue;
            }

            $value = trim((string) ($entry[(string) $field->id] ?? ''));

            if (is_email($value)) {
                return $value;
            }
        }

        return null;
    }

    /** Reproduit le repli `wordpress@<domaine>` de wp_mail() (pluggable.php). */
    private function wordPressFallbackAddress(): string
    {
        $host = wp_parse_url(network_home_url(), PHP_URL_HOST);

        if (!is_string($host)) {
            return 'wordpress@';
        }

        if (str_starts_with($host, 'www.')) {
            $host = substr($host, 4);
        }

        return 'wordpress@' . $host;
    }

    /** @param array<string, mixed> $notification */
    private function setting(array $notification, string $key): string
    {
        return trim((string) ($notification[$key] ?? ''));
    }
}
