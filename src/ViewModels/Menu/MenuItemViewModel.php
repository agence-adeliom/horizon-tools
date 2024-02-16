<?php

declare(strict_types=1);

namespace LucasVigneron\SageTools\ViewModels\Menu;

class MenuItemViewModel
{
	public int $id;
	public string $title;
	public string $url;
	public int $parentId;
	public array $children = [];
	public bool $hasChildren = false;
	public false|array $customFields = [];

	public function __construct(\WP_Post $menuItem)
	{
		$this->id = $menuItem->ID;
		$this->title = $menuItem->title;
		$this->url = $menuItem->url;
		$this->parentId = (int)$menuItem->menu_item_parent;

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
