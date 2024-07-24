<?php

declare(strict_types=1);

namespace LucasVigneron\SageTools\Providers;

use Extended\ACF\Location;
use LucasVigneron\SageTools\Blocks\AbstractBlock;
use LucasVigneron\SageTools\Services\ClassService;
use LucasVigneron\SageTools\Services\FileService;
use LucasVigneron\SageTools\Taxonomies\AbstractTaxonomy;
use Roots\Acorn\Sage\SageServiceProvider;
use Roots\Acorn\Exceptions\SkipProviderException;

class PostTypeServiceProvider extends SageServiceProvider
{
	private ?array $templates = null;

	public function boot(): void
	{
		add_filter('register_post_type_args', [$this, 'setTemplates'], accepted_args: 2);

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

	public function setTemplates($args, $postType)
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

		foreach (ClassService::getAllCustomTemplateClasses() as $templateClass) {
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
		try {
			$templates = $this->getTemplatesPerPostType();

			foreach (FileService::getClassesPathsFromPath(get_template_directory() . '/app/PostTypes') as $classPath) {
				require_once $classPath;
			}

			foreach (ClassService::getAllCustomPostTypeClasses() as $postTypeClass) {
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
		} catch (\Exception $e) {
			throw new SkipProviderException($e->getMessage());
		}
	}

	private function initTaxonomies(): void
	{
		try {
			foreach (FileService::getClassesPathsFromPath(get_template_directory() . '/app/Taxonomies') as $classPath) {
				require_once $classPath;
			}

			foreach (ClassService::getAllCustomTaxonomyClasses() as $taxonomyClass) {
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
										'key' => 'group_taxonomy_' . $class::$slug,
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
		} catch (\Exception $e) {
			throw new SkipProviderException($e->getMessage());
		}
	}
}
