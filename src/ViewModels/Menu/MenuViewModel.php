<?php

declare(strict_types=1);

namespace Adeliom\HorizonTools\ViewModels\Menu;

class MenuViewModel
{
    public int $id;
    public string $name;
    public string $slug;
    public array $items = [];

    public function __construct(\WP_Term|string $menu)
    {
        if (is_string($menu)) {
            if (($locations = get_nav_menu_locations()) && !empty($locations[$menu])) {
                // Force the `nav_menu` taxonomy: get_term() without it can fall back to
                // an unrelated taxonomy and return a WP_Error, which is_object() would
                // happily let through into property access below.
                $menu = get_term((int) $locations[$menu], 'nav_menu');
            }

            if (is_string($menu) && is_admin()) {
                throw new \Exception('Menu not found');
            }
        }

        // Only hydrate from a real nav_menu term; WP_Error / null / leftover string slugs
        // leave the model un-hydrated so templates can fall back via isset($menu->id).
        if (!$menu instanceof \WP_Term) {
            return;
        }

        $this->id = $menu->term_id;
        $this->name = $menu->name;
        $this->slug = $menu->slug;

        $this->setItems();
    }

    private function setItems(?array &$flatItems = null, ?MenuItemViewModel $parent = null, int $level = 0): void
    {
        if (null === $flatItems) {
            $flatItems = wp_get_nav_menu_items($this->id);
        }

        if (empty($flatItems)) {
            return;
        }

        $parentId = $parent ? $parent->id : 0;

        foreach ($flatItems as $flatItem) {
            if ($flatItem->menu_item_parent == $parentId) {
                $item = new MenuItemViewModel($flatItem, $level);

                $this->setItems($flatItems, $item, $level + 1);

                if ($parent) {
                    $parent->addItem($item);
                } else {
                    $this->items[] = $item;
                }
            }
        }
    }
}
