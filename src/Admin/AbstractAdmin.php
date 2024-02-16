<?php

declare(strict_types=1);

namespace LucasVigneron\SageTools\Admin;

abstract class AbstractAdmin
{
	abstract public function getTitle(): string;

	public function getSlug(): string
	{
		return sanitize_title($this->getTitle());
	}

	public function isOptionPage(): bool
	{
		return false;
	}

	public function getOptionPageParams(): array
	{
		return [
			'page_title' => $this->getTitle(),
			'menu_title' => $this->getTitle(),
			'menu_slug' => $this->getSlug(),
			'capability' => 'edit_theme_options',
			'autoload' => true,
			'icon_url' => 'dashicons-welcome-view-site',
		];
	}

	public function getFields(): ?iterable
	{
		return null;
	}

	abstract public function getLocation(): iterable;
}
