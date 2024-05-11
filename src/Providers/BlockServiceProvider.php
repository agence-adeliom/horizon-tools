<?php

declare(strict_types=1);

namespace LucasVigneron\SageTools\Providers;

use Extended\ACF\Location;
use LucasVigneron\SageTools\Blocks\AbstractBlock;
use LucasVigneron\SageTools\Enum\BlockCategoriesEnum;
use LucasVigneron\SageTools\Services\ClassService;
use LucasVigneron\SageTools\Services\FileService;
use Roots\Acorn\Exceptions\SkipProviderException;
use Roots\Acorn\Sage\SageServiceProvider;

class BlockServiceProvider extends SageServiceProvider
{
	public const UNREGISTER_DEFAULT_BLOCKS = true;
	private const THEME_BLOCK_CATEGORIES_CLASS = 'App\Enum\BlockCategoriesEnum';

	public function boot(): void
	{
		$this->initBlocks();

		if (self::UNREGISTER_DEFAULT_BLOCKS) {
			add_filter('allowed_block_types_all', [$this, 'unregisterBlocks'], 10, 2);
			add_filter('block_categories_all', [$this, 'registerCustomBlockCategories'], 10, 2);
		}
	}

	private function initBlocks(): void
	{
		foreach (FileService::getClassesPathsFromPath(get_template_directory() . '/app/Blocks') as $classPath) {
			require_once $classPath;
		}

		$blockClasses = array_filter(get_declared_classes(), function ($class) {
			return is_subclass_of($class, AbstractBlock::class);
		});

		foreach ($blockClasses as $blockClass) {
			if ($className = ClassService::getClassNameFromFullName($blockClass)) {
				if (!str_starts_with($className, 'Abstract')) {
					$class = new $blockClass();

					if (function_exists('register_extended_field_group')) {

						$category = ClassService::getFolderNameFromFullName($blockClass);

						register_extended_field_group([
							'key' => $class::$slug,
							'title' => $class::$title,
							'fields' => $class->getFields() ? iterator_to_array($class->getFields(), false) : [],
							'location' => [
								Location::where('block', 'acf/' . $class::$slug),
							],
						]);

						acf_register_block_type([
							'name' => $class::$slug,
							'title' => $class::$title,
							'category' => $category,
							'mode' => $class::$mode,
							'description' => $class::$description,
							'icon' => $class::$icon,
							'post_types' => $class->getPostTypes(),
							'render_callback' => function ($block) use ($class, $category) {

								$template = 'blocks/' . $category . '/' . str_replace('acf/', '', $block['name']);

								if (file_exists(get_template_directory() . '/resources/views/' . $template . '.blade.php')) {

									if(isset($block['data']['_is_preview'])) {
										echo "<img style='width:100%' src='". get_template_directory_uri() . '/resources/images/admin/blocks/' . $category . "/" . $class::$slug . ".jpg' alt='Preview'>";
										return;
									}
									
									echo view('blocks/' . $category . '/' . str_replace('acf/', '', $block['name']), [
										'block' => $block,
										'fields' => get_fields(),
										'context' => $class->addToContext(),
									]);
								} else {
									throw new SkipProviderException('Template not found: ' . $template . '.blade.php');
								}
							},
							'supports' => $class->getSupports(),
							'example' => $class->getExample(),
						]);
				
						add_filter(sprintf('render_block_%s', $class->getFullName()), function ($blockContent) use ($class, $category) {
							$class->renderBlockCallback();

							return $blockContent;
						});
					}
				}
			}
		}
	}

	public function unregisterBlocks($allowedBlocks, $here): array
	{
		// Get only declared classes starting by App\Blocks
		$classes = array_filter(get_declared_classes(), function ($class) {
			return str_starts_with($class, 'App\Blocks') && $class !== 'App\Blocks\AbstractBlock';
		});

		return array_values(array_map(function ($class) {
			return 'acf/' . $class::$slug;
		}, $classes));
	}

	public function registerCustomBlockCategories($categories, $post): array
	{
		$this->registerCategoriesFromCases(BlockCategoriesEnum::class, $categories);

		if (class_exists(self::THEME_BLOCK_CATEGORIES_CLASS)) {
			$this->registerCategoriesFromCases(self::THEME_BLOCK_CATEGORIES_CLASS, $categories);
		}


		return $categories;
	}

	private function registerCategoriesFromCases($enum, &$categories)
	{
		if (method_exists($enum, 'cases')) {
			foreach ($enum::cases() as $case) {
				$icon = 'admin-post';
				$title = $case->value;
				$association = null;

				if (defined($enum . '::ASSOCIATIONS')) {
					if (isset($enum::ASSOCIATIONS[$case->value]) && $association = $enum::ASSOCIATIONS[$case->value]) {
						if (isset($association['title'], $association['icon'])) {
							$title = $association['title'];
							$icon = $association['icon'];
						}
					}
				}

				$categories[] = [
					'slug' => $case->value,
					'title' => $title,
					'icon' => $icon,
				];
			}
		}
	}
}
