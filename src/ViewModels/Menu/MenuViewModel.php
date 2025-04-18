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
            if (($locations = get_nav_menu_locations()) && isset($locations[$menu])) {
                $menu = get_term($locations[$menu]);
            }

            if (is_string($menu)) {
                if (is_admin()) {
                    throw new \Exception('Menu not found');
                }
            }
        }

        if (is_object($menu)) {
            $this->id = $menu->term_id;
            $this->name = $menu->name;
            $this->slug = $menu->slug;

            $this->setItems();
        }
    }

    private function setItems(?array &$flatItems = null, ?MenuItemViewModel $parent = null, int $level = 0): void
    {
        if (null === $flatItems) {
            $flatItems = wp_get_nav_menu_items($this->id);
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
