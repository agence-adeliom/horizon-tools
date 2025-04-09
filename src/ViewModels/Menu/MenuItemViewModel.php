<?php

declare(strict_types=1);

namespace Adeliom\HorizonTools\ViewModels\Menu;

class MenuItemViewModel
{
    public int $id;
    public string $title;
    public string $description;
    public string $url;
    public string $target;
    public int $parentId;
    public array $classes = [];
    public array $children = [];
    public bool $hasChildren = false;
    public false|array $customFields = [];
    public ?int $level = null;

    public function __construct(\WP_Post $menuItem, ?int $level = null)
    {
        $this->id = $menuItem->ID;
        $this->title = $menuItem->title;
        $this->description = $menuItem->description;
        $this->url = $menuItem->url;
        $this->target = $menuItem->target;
        $this->classes = $menuItem->classes;
        $this->parentId = (int) $menuItem->menu_item_parent;
        $this->level = $level;

        if ($customFields = get_fields($menuItem)) {
            $this->customFields = $customFields;
        }
    }

    public function addItem(MenuItemViewModel $item): void
    {
        $this->children[] = $item;
        $this->hasChildren = true;
    }
}
