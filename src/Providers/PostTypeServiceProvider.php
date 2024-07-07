<?php

declare(strict_types=1);

namespace LucasVigneron\SageTools\Providers;

use Extended\ACF\Location;
use LucasVigneron\SageTools\Blocks\AbstractBlock;
use LucasVigneron\SageTools\PostTypes\AbstractPostType;
use LucasVigneron\SageTools\Services\ClassService;
use LucasVigneron\SageTools\Services\FileService;
use LucasVigneron\SageTools\Taxonomies\AbstractTaxonomy;
use LucasVigneron\SageTools\Templates\AbstractTemplate;
use Roots\Acorn\Sage\SageServiceProvider;

class PostTypeServiceProvider extends SageServiceProvider
{
	private ?array $templates = null;

	public function boot(): void
	{
		add_filter('register_post_type_args', [$this, 'test'], accepted_args: 2);

		$this->initPostTypes();
		$this->initTaxonomies();
	}

	public function getTemplates(): array
	{
		if (null === $this->templates) {
			$this->templates = $this->getTemplatesPerPostType();
		}

		return $this->templates;
	}

	public function test($args, $postType)
	{
		$this->getTemplates();

		if (!isset($args['template'])) {
			if (isset($this->getTemplates()[$postType])) {
				$args['template'] = $this->getTemplates()[$postType];
			}
		}

		return $args;
	}

	private function getTemplatesPerPostType(): array
	{
		$data = [];
		foreach (FileService::getClassesPathsFromPath(get_template_directory() . '/app/Templates') as $classPath) {
			require_once $classPath;
		}

		$templateClasses = array_filter(get_declared_classes(), function ($class) {
			return is_subclass_of($class, AbstractTemplate::class);
		});

		foreach ($templateClasses as $templateClass) {
			if ($className = ClassService::getClassNameFromFullName($templateClass)) {
				if (!str_starts_with($className, 'Abstract')) {
					$class = new $templateClass();

					if ($class->getPostTypes() && $class->getBlocks()) {
						foreach ($class->getPostTypes() as $postType) {
							$template = [];

							foreach ($class->getBlocks() as $block => $fields) {
								$blockClass = new $block();
								if ($blockClass instanceof AbstractBlock) {
									$template[] = ['acf/' . $blockClass::$slug, $fields];
								}
							}

							$data[$postType] = $template;
						}
					}
				}
			}
		}

		return $data;
	}

	private function initPostTypes(): void
	{
		$templates = $this->getTemplatesPerPostType();

		foreach (FileService::getClassesPathsFromPath(get_template_directory() . '/app/PostTypes') as $classPath) {
			require_once $classPath;
		}

		$postTypeClasses = array_filter(get_declared_classes(), function ($class) {
			return is_subclass_of($class, AbstractPostType::class);
		});

		foreach ($postTypeClasses as $postTypeClass) {
			if ($className = ClassService::getClassNameFromFullName($postTypeClass)) {
				if (!str_starts_with($className, 'Abstract')) {
					$class = new $postTypeClass();

					if ($config = $class->getConfig()) {
						if (isset($config['post_type'])) {
							register_post_type($config['post_type'], $config['args']);
						}
					}

					if (function_exists('register_extended_field_group')) {
						if ($fields = $class->getFields()) {
							if ($customFields = iterator_to_array($fields, false)) {
								register_extended_field_group([
									'key' => 'group_' . $class::$slug,
									'title' => $class->getFieldsTitle(),
									'fields' => $customFields,
									'style' => $class->getStyle(),
									'location' => [
										Location::where('post_type', $class::$slug)
									],
									'position' => $class->getPosition(),
									'label_placement' => $class->getLabelPlacement(),
									'instruction_placement' => $class->getInstructionPlacement(),
									'hide_on_screen' => $class->getHideOnScreen(),
									'menu_order' => $class->getMenuOrder(),
								]);
							}
						}
					}
				}
			}
		}
	}

	private function initTaxonomies(): void
	{
		foreach (FileService::getClassesPathsFromPath(get_template_directory() . '/app/Taxonomies') as $classPath) {
			require_once $classPath;
		}

		$taxonomyClasses = array_filter(get_declared_classes(), function ($class) {
			return is_subclass_of($class, AbstractTaxonomy::class);
		});


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
									'key' => 'group_' . $class::$slug,
									'title' => $class->getFieldsTitle(),
									'fields' => $customFields,
									'style' => $class->getStyle(),
									'location' => [
										Location::where('taxonomy', $class::$slug)
									],
									'position' => $class->getPosition(),
									'label_placement' => $class->getLabelPlacement(),
									'instruction_placement' => $class->getInstructionPlacement(),
									'hide_on_screen' => $class->getHideOnScreen(),
									'menu_order' => $class->getMenuOrder(),
								]);
							}
						}
					}
				}
			}
		}
	}
}
