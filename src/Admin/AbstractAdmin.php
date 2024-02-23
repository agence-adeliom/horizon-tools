<?php

declare(strict_types=1);

namespace LucasVigneron\SageTools\Admin;

use Roots\Acorn\Exceptions\SkipProviderException;

abstract class AbstractAdmin
{
	public static ?string $title = null;

	public function __construct()
	{
		if (null === $this::$title) {
			throw new SkipProviderException(static::class . ' : You must define a title for your admin');
		}
	}

	public function getSlug(): string
	{
		return sanitize_title($this::$title);
	}

	public function isOptionPage(): bool
	{
		return false;
	}

	public function getOptionPageParams(): array
	{
		return [
			'page_title' => $this::$title,
			'menu_title' => $this::$title,
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
