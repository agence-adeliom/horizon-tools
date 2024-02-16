<?php

declare(strict_types=1);

namespace LucasVigneron\SageTools\Providers;

use Extended\ACF\Location;
use LucasVigneron\SageTools\Blocks\AbstractBlock;
use LucasVigneron\SageTools\Services\ClassService;
use LucasVigneron\SageTools\Services\FileService;
use Roots\Acorn\Sage\SageServiceProvider;

class BlockServiceProvider extends SageServiceProvider
{
	public const UNREGISTER_DEFAULT_BLOCKS = true;

	public function boot(): void
	{
		$this->initBlocks();

		if (self::UNREGISTER_DEFAULT_BLOCKS) {
			add_filter('allowed_block_types_all', [$this, 'unregisterBlocks'], 10, 2);
		}
	}

	private function initBlocks(): void
	{
		$classes = get_declared_classes();

		foreach (FileService::getClassesPathsFromPath(get_template_directory() . '/app/Blocks') as $classPath) {
			require_once $classPath;
		}

		$blockClasses = array_values(array_diff(get_declared_classes(), $classes));

		foreach ($blockClasses as $blockClass) {
			if ($className = ClassService::getClassNameFromFullName($blockClass)) {
				if (!str_starts_with($className, 'Abstract')) {
					$class = new $blockClass();

					if (is_subclass_of($class, AbstractBlock::class)) {
						if (function_exists('register_extended_field_group')) {
							register_extended_field_group([
								'title' => $class->getBlockTitle(),
								'fields' => $class->getBlockFields() ? iterator_to_array($class->getBlockFields(), false) : [],
								'location' => [
									Location::where('block', 'acf/' . $class->getBlockName()),
								],
							]);

							acf_register_block([
								'name' => $class->getBlockName(),
								'title' => $class->getBlockTitle(),
								'category' => $class->getBlockCategory(),
								'description' => null,
								'render_callback' => function ($block) use ($class) {
									echo view('blocks/' . $class->getBlockCategory() . '/' . str_replace('acf/', '', $block['name']), [
										'block' => $block,
										'fields' => get_fields(),
										'context' => $class->addToContext(),
									]);
								}
							]);

							add_filter(sprintf('render_block_%s', $class->getFullBlockName()), function ($blockContent) use ($class) {
								$class->renderBlockCallback();

								return $blockContent;
							});
						}
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
			return 'acf/' . $class::getBlockName();
		}, $classes));
	}
}
