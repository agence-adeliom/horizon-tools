<?php

declare(strict_types=1);

namespace LucasVigneron\SageTools\Providers;

use Illuminate\Console\Command;
use LucasVigneron\SageTools\Services\FileService;
use Roots\Acorn\Console\Commands\AcornInitCommand;
use Roots\Acorn\Exceptions\SkipProviderException;
use Roots\Acorn\Sage\SageServiceProvider;

class CommandsServiceProvider extends SageServiceProvider
{
	public function boot()
	{
		try {
			$path = __DIR__ . '/../Console/Commands';

			foreach (FileService::getClassesPathsFromPath($path) as $classPath) {
				require_once $classPath;
			}

			$commandsClasses = array_filter(get_declared_classes(), function ($class) {
				return is_subclass_of($class, Command::class);
			});

			$this->commands($commandsClasses);
		} catch (\Exception $e) {
			throw new SkipProviderException($e->getMessage());
		}
	}
}