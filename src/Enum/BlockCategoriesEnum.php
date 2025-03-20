<?php

declare(strict_types=1);

namespace Adeliom\HorizonTools\Enum;

enum BlockCategoriesEnum: string
{
    case CONTENT = 'content';

    case HERO = 'hero';

    case LISTING = 'listing';

    case CTA = 'cta';

    case ACTION = 'action';

    case TRIP = 'trip';

    case NAVIGATION = 'navigation';

    case RELATION = 'relation';

    case GENERIC = 'generic';

    case LATEST = 'latest';

    case OTHERS = 'others';

    case DOWNLOAD = 'download';

    case ACCORDIONS = 'accordions';

    case TESTIMONIALS = 'testimonials';

    case REASSURANCE = 'reassurance';

    const ASSOCIATIONS = [
        self::HERO->value => [
            'title' => 'Haut de page',
            'icon' => 'editor-kitchensink',
        ],
        self::NAVIGATION->value => [
            'title' => 'Navigation',
            'icon' => 'menu',
        ],
        self::CONTENT->value => [
            'title' => 'Contenu',
            'icon' => 'admin-post',
        ],
        self::GENERIC->value => [
            'title' => 'Texte et Images',
            'icon' => 'align-right',
        ],
        self::CTA->value => [
            'title' => 'Actions',
            'icon' => 'button',
        ],
        self::ACTION->value => [
            'title' => 'Actions',
            'icon' => 'button',
        ],
        self::LISTING->value => [
            'title' => 'Listes',
            'icon' => 'editor-ul',
        ],
        self::LATEST->value => [
            'title' => 'Remontées automatiques',
            'icon' => 'page',
        ],
        self::ACCORDIONS->value => [
            'title' => 'Accordéons',
            'icon' => 'arrow-down-alt2',
        ],
        self::RELATION->value => [
            'title' => 'Relation',
            'icon' => 'networking',
        ],
        self::DOWNLOAD->value => [
            'title' => 'Téléchargement',
            'icon' => 'download',
        ],
        self::TESTIMONIALS->value => [
            'title' => 'Témoignages',
            'icon' => 'format-quote',
        ],
        self::OTHERS->value => [
            'title' => 'Autres',
            'icon' => 'admin-generic',
        ],

        self::REASSURANCE->value => [
            'title' => 'Réassurance',
            'icon' => 'admin-generic',
        ],
    ];
}
