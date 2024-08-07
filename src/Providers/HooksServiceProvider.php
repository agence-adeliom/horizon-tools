<?php

declare(strict_types=1);

namespace Adeliom\HorizonTools\Providers;

use Adeliom\HorizonTools\Hooks\AbstractHook;
use Adeliom\HorizonTools\Hooks\DefaultGutenbergHooks;
use Adeliom\HorizonTools\Hooks\DefaultWordPressHooks;
use Adeliom\HorizonTools\Hooks\PostHooks;
use Adeliom\HorizonTools\Hooks\RankMathHooks;
use Adeliom\HorizonTools\Hooks\WysiwygHooks;
use Adeliom\HorizonTools\Services\ClassService;
use Adeliom\HorizonTools\Services\FileService;
use Roots\Acorn\Sage\SageServiceProvider;
use Roots\Acorn\Exceptions\SkipProviderException;

class HooksServiceProvider extends SageServiceProvider
{
	public function boot(): void
	{
		$this->initHooks();
	}

	private function initHooks(): void
	{
		try {
			foreach (FileService::getClassesPathsFromPath(get_template_directory() . '/app/Hooks') as $classPath) {
				require_once $classPath;
			}

			$defaultClasses = [
				PostHooks::class,
				RankMathHooks::class,
				DefaultGutenbergHooks::class,
				DefaultWordPressHooks::class,
                WysiwygHooks::class,
			];

			$hookClasses = array_filter(get_declared_classes(), function ($class) {
				return is_subclass_of($class, AbstractHook::class);
			});


			foreach (array_merge($hookClasses, $defaultClasses) as $hookClass) {
				if ($className = ClassService::getClassNameFromFullName($hookClass)) {
					if (!str_starts_with($className, 'Abstract')) {
						$class = new $hookClass();

						if (method_exists($class, 'init')) {
							$class->init();
						}
					}
				}
			}
		} catch (\Exception $e) {
			throw new SkipProviderException($e->getMessage());
		}
	}
}