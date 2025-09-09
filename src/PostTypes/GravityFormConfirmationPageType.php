<?php

declare(strict_types=1);

namespace Adeliom\HorizonTools\PostTypes;

class GravityFormConfirmationPageType extends AbstractPostType
{
    public static ?string $slug = 'gf-confirmation-page';

    public function getConfig(array $config = []): array
    {
        $config['args'] = [
            'label' => 'P. de confirmation',
            'labels' => [
                'name' => 'Pages de confirmation',
                'singular_name' => 'Page de confirmation',
                'menu_name' => 'P. de confirmation',
                'add_new' => 'Ajouter une page de confirmation',
                'add_new_item' => 'Ajouter une nouvelle page de confirmation',
                'edit_item' => 'Modifier la page de confirmation',
                'new_item' => 'Nouvelle page de confirmation',
                'view_item' => 'Voir la page de confirmation',
                'view_items' => 'Voir les pages de confirmation',
                'search_items' => 'Rechercher une page de confirmation',
                'not_found' => 'Aucune page de confirmation trouvée',
                'not_found_in_trash' => 'Aucune page de confirmation trouvée dans la corbeille',
                'all_items' => 'Toutes les pages de confirmation',
                'archives' => 'Archives des pages de confirmation',
            ],
            'rewrite' => false,
            'hierarchical' => false,
            'public' => true,
            'publicly_queryable' => false,
        ];

        return parent::getConfig($config);
    }
}
