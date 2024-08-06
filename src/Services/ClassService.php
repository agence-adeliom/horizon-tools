<?php

declare(strict_types=1);

namespace Adeliom\HorizonTools\Services;

use Adeliom\HorizonTools\Blocks\AbstractBlock;
use Adeliom\HorizonTools\PostTypes\AbstractPostType;
use Adeliom\HorizonTools\Taxonomies\AbstractTaxonomy;
use Adeliom\HorizonTools\Templates\AbstractTemplate;

class ClassService
{
	public static function getClassNameFromFullName(string $fullName): ?string
	{
		preg_match("/\\\\([^\\\\]+)$/", $fullName, $m);

		if (isset($m[1])) {
			return $m[1];
		}

		return null;
	}

	public static function getFolderNameFromFullName(string $fullName): ?string
	{
		$string = explode('\\', $fullName);
		return strtolower($string[count($string) - 2]);
	}

	public static function slugifyClassName(string $className): string
	{
		return strtolower(preg_replace('/(?<!^)[A-Z]/', '-$0', $className));
	}

	public static function getAllCustomPostTypeClasses(): array
	{
		return array_filter(get_declared_classes(), function ($class) {
			return is_subclass_of($class, AbstractPostType::class);
		});
	}

	public static function getAllCustomBlockClasses(): array
	{
		return array_filter(get_declared_classes(), function ($class) {
			return is_subclass_of($class, AbstractBlock::class);
		});
	}

	public static function getAllCustomTaxonomyClasses(): array
	{
		return array_filter(get_declared_classes(), function ($class) {
			return is_subclass_of($class, AbstractTaxonomy::class);
		});
	}

	public static function getAllCustomTemplateClasses(): array
	{
		return array_filter(get_declared_classes(), function ($class) {
			return is_subclass_of($class, AbstractTemplate::class);
		});
	}
}
