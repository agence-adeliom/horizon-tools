<?php

declare(strict_types=1);

namespace LucasVigneron\SageTools\Services;

class BudService
{
	private static function getPublicDirectory(): string
	{
		return get_template_directory() . '/public/';
	}

	private static function getTemplateName(): string
	{
		$parts = explode('/', get_template_directory());

		return last($parts);
	}

	private static function getManifest(): array
	{
		$path = self::getPublicDirectory() . 'manifest.json';

		if (file_exists($path)) {
			return json_decode(file_get_contents($path), true);
		} else {
			return [];
		}
	}

	private static function getManifestAssociation(string $handle): false|string
	{
		$manifest = self::getManifest();

		if (!empty($manifest)) {
			if (in_array($handle, array_keys($manifest))) {
				$localPath = $manifest[$handle];

				if (file_exists(get_template_directory() . '/public/' . $localPath)) {
					return '/public/' . $localPath;
				} else {
					return false;
				}

				return $manifest[$handle];
			} else {
				return false;
			}
		}
	}

	public static function getPath(string $handle): string
	{
		if ($path = self::getManifestAssociation($handle)) {
			return '/app/themes/' . self::getTemplateName() . $path;
		} else {
			return '';
		}
	}

	public static function getUrl(string $handle): false|string
	{
		if ($path = self::getManifestAssociation($handle)) {
			return get_template_directory_uri() . $path;
		} else {
			return false;
		}
	}

	public static function getUrlByRegex(string $regex): false|string
	{
		$publicDirectory = self::getPublicDirectory();
		$cssDirectory = $publicDirectory . 'css/';

		foreach (scandir($cssDirectory) as $fileName) {
			if (preg_match($regex, $fileName)) {
				return get_template_directory_uri() . '/public/css/' . $fileName;
			}
		}

		return false;
	}
}