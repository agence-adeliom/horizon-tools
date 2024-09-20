<?php

declare(strict_types=1);

namespace Adeliom\HorizonTools\Services;

use Adeliom\HorizonTools\Admin\AbstractAdmin;
use Adeliom\HorizonTools\Blocks\AbstractBlock;
use Adeliom\HorizonTools\Hooks\AbstractHook;
use Adeliom\HorizonTools\PostTypes\AbstractPostType;
use Adeliom\HorizonTools\Taxonomies\AbstractTaxonomy;
use Adeliom\HorizonTools\Templates\AbstractTemplate;

class CommandService
{
	/**
	 * @param string $argument
	 * @return array<string, string|array>
	 */
	public static function getFolderStructure(string $argument): array
	{
		$folders = explode('/', $argument);
		$className = last($folders);
		array_pop($folders);

		return [
			'class' => $className,
			'folders' => $folders,
			'path' => $argument . '.php',
		];
	}

	public static function handleClassCreation(string $type, string $filepath, string $path, array $folders, string $className, string $template, ?string $slug = null, ?string $parentClass = null): string
	{
		if (file_exists($filepath)) {
			return 'already_exists';
		}

		if (!file_exists($path)) {
			mkdir($path, 0755, true);
		}

		foreach ($folders as $folder) {
			$path .= $folder . '/';
			if (!file_exists($path)) {
				mkdir($path, 0755, true);
			}
		}

		if (null === $slug) {
			$slug = ClassService::slugifyClassName($className);

			if (str_ends_with($slug, '-block')) {
				$slug = substr($slug, 0, -6);
			}
		}

		$namespaceEnd = implode('\\', $folders);

		$folder = match ($type) {
			AbstractBlock::class => 'Blocks',
			AbstractTaxonomy::class => 'Taxonomies',
			AbstractPostType::class => 'PostTypes',
			AbstractTemplate::class => 'Templates',
			AbstractAdmin::class => 'Admin',
			AbstractHook::class => 'Hooks',
		};

		// Create empty file
		file_put_contents($filepath, str_replace([
			'%%NAMESPACE%%',
			'%%CLASS%%',
			'%%PARENT_NAMESPACE%%',
			'%%PARENT%%',
			'%%SLUG%%',
			'%%TAXONOMY_NAME%%',
			'%%CPT_NAME%%',
			'%%BLOCK_NAME%%',
			'%%ADMIN_NAME%%',
			'%%ADMIN_SLUG%%',
			'%%PARENT_SLUG_STATIC%%',
		], [
			'App\\' . $folder . ($namespaceEnd ? '\\' . $namespaceEnd : ''),
			$className,
			$type,
			ClassService::getClassNameFromFullName($type),
			$slug,
			$className,
			$className,
			$className,
			$className,
			sanitize_title($className),
			$parentClass !== null ? sprintf("\%s::%s", $parentClass, '$slug') : 'null',
		], $template));

		return 'success';
	}
}