<?php

declare(strict_types=1);

namespace LucasVigneron\SageTools\Providers;

use LucasVigneron\SageTools\PostTypes\AbstractPostType;
use LucasVigneron\SageTools\Services\ClassService;
use LucasVigneron\SageTools\Services\FileService;
use LucasVigneron\SageTools\Taxonomies\AbstractTaxonomy;
use Roots\Acorn\Sage\SageServiceProvider;

class PostTypeServiceProvider extends SageServiceProvider
{
	public function boot(): void
	{
		$this->initPostTypes();
		$this->initTaxonomies();
	}

	private function initPostTypes(): void
	{
		$classes = get_declared_classes();

		foreach (FileService::getClassesPathsFromPath(get_template_directory() . '/app/PostTypes') as $classPath) {
			require_once $classPath;
		}

		$postTypeClasses = array_values(array_diff(get_declared_classes(), $classes));

		foreach ($postTypeClasses as $postTypeClass) {
			if ($className = ClassService::getClassNameFromFullName($postTypeClass)) {
				if (!str_starts_with($className, 'Abstract')) {
					$class = new $postTypeClass();

					if (is_subclass_of($class, AbstractPostType::class)) {
						if ($config = $class->getConfig()) {
							if (isset($config['post_type'])) {
								register_post_type($config['post_type'], $config['args']);
							}
						}

						if (function_exists('register_extended_field_group')) {
							if ($fields = $class->getFields()) {
								if ($customFields = iterator_to_array($fields, false)) {
									register_extended_field_group([
										'title' => $class->getFieldsTitle(),
										'fields' => $customFields,
										'location' => iterator_to_array($class->getFieldsLocation(), false),
										'position' => $class->getFieldsPosition(),
									]);
								}
							}
						}
					}
				}
			}
		}
	}

	private function initTaxonomies(): void
	{
		$classes = get_declared_classes();

		foreach (FileService::getClassesPathsFromPath(__DIR__ . '/../Taxonomies') as $classPath) {
			require_once $classPath;
		}

		$taxonomyClasses = array_values(array_diff(get_declared_classes(), $classes));

		foreach ($taxonomyClasses as $taxonomyClass) {
			if ($className = ClassService::getClassNameFromFullName($taxonomyClass)) {
				if (!str_starts_with($className, 'Abstract')) {
					$class = new $taxonomyClass;

					if (is_subclass_of($class, AbstractTaxonomy::class)) {
						if ($config = $class->getConfig()) {
							if (isset($config['taxonomy'])) {
								register_taxonomy($config['taxonomy'], $config['object_type'], $config['args']);
							}
						}

						if (function_exists('register_extended_field_group')) {
							if ($class->getFields() && $customFields = iterator_to_array($class->getFields(), false)) {
								register_extended_field_group([
									'title' => $class->getFieldsTitle(),
									'fields' => $customFields,
									'location' => iterator_to_array($class->getFieldsLocation(), false),
								]);
							}
						}
					}
				}
			}
		}
	}
}
