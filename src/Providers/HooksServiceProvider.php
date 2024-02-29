<?php

declare(strict_types=1);

namespace LucasVigneron\SageTools\Providers;

use LucasVigneron\SageTools\Hooks\AbstractHook;
use LucasVigneron\SageTools\Services\ClassService;
use LucasVigneron\SageTools\Services\FileService;
use Roots\Acorn\Sage\SageServiceProvider;

class HooksServiceProvider extends SageServiceProvider
{
	public function boot(): void
	{
		$this->initHooks();
	}

	private function initHooks(): void
	{
		foreach (FileService::getClassesPathsFromPath(get_template_directory() . '/app/Hooks') as $classPath) {
			require_once $classPath;
		}

		$hookClasses = array_filter(get_declared_classes(), function ($class) {
			return is_subclass_of($class, AbstractHook::class);
		});

		foreach ($hookClasses as $hookClass) {
			if ($className = ClassService::getClassNameFromFullName($hookClass)) {
				if (!str_starts_with($className, 'Abstract')) {
					$class = new $hookClass();

					if (method_exists($class, 'init')) {
						$class->init();
					}
				}
			}
		}
	}
}