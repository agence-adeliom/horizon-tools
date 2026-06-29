<?php

declare(strict_types=1);

namespace Adeliom\HorizonTools\PostTypes;

use Adeliom\HorizonTools\Services\PostService;
use Extended\ACF\Fields\Group;
use Extended\ACF\Fields\PostObject;

if (!class_exists('Adeliom\HorizonTools\PostTypes\GravityFormConfirmationPageType')) {
    class GravityFormConfirmationPageType extends AbstractPostType
    {
        public static ?string $slug = 'gf-confirmation-page';

        public const FIELD_CONFIGURATION = 'configuration';
        public const FIELD_PARENT = 'parentPage';

        public function getConfig(array $config = []): array
        {
            $config['args'] = [
                'label' => __('P. de confirmation', 'horizon-tools'),
                'labels' => [
                    'name' => __('Pages de confirmation', 'horizon-tools'),
                    'singular_name' => __('Page de confirmation', 'horizon-tools'),
                    'menu_name' => __('Confirmations', 'horizon-tools'),
                    'add_new' => __('Ajouter une page de confirmation', 'horizon-tools'),
                    'add_new_item' => __('Ajouter une nouvelle page de confirmation', 'horizon-tools'),
                    'edit_item' => __('Modifier la page de confirmation', 'horizon-tools'),
                    'new_item' => __('Nouvelle page de confirmation', 'horizon-tools'),
                    'view_item' => __('Voir la page de confirmation', 'horizon-tools'),
                    'view_items' => __('Voir les pages de confirmation', 'horizon-tools'),
                    'search_items' => __('Rechercher une page de confirmation', 'horizon-tools'),
                    'not_found' => __('Aucune page de confirmation trouvée', 'horizon-tools'),
                    'not_found_in_trash' => __('Aucune page de confirmation trouvée dans la corbeille', 'horizon-tools'),
                    'all_items' => __('Toutes les pages de confirmation', 'horizon-tools'),
                    'archives' => __('Archives des pages de confirmation', 'horizon-tools'),
                ],
                'rewrite' => false,
                'hierarchical' => false,
                'public' => true,
                'publicly_queryable' => false,
                'menu_icon' => 'dashicons-yes-alt',
            ];

            return parent::getConfig($config);
        }

        public function getPosition(): string
        {
            return 'side';
        }

        public function getFields(): ?iterable
        {
            yield Group::make(__('Configuration de la page', 'horizon-tools'), self::FIELD_CONFIGURATION)->fields([
                PostObject::make(__('Page parent', 'horizon-tools'), self::FIELD_PARENT)
                    ->postTypes(PostService::getAllPostTypeSlugs())
                    ->nullable(),
            ]);
        }
    }
}
