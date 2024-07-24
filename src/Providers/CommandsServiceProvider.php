<?php

declare(strict_types=1);

namespace LucasVigneron\SageTools\Providers;

use LucasVigneron\SageTools\Console\Commands\MakeBlock;
use LucasVigneron\SageTools\Console\Commands\MakePostType;
use LucasVigneron\SageTools\Console\Commands\MakeTaxonomy;
use Roots\Acorn\Exceptions\SkipProviderException;
use Roots\Acorn\Sage\SageServiceProvider;

class CommandsServiceProvider extends SageServiceProvider
{
	public function boot()
	{
		try {
			$this->commands([
				MakeBlock::class,
				MakePostType::class,
				MakeTaxonomy::class,
			]);
		} catch (\Exception $e) {
			throw new SkipProviderException($e->getMessage());
		}
	}
}