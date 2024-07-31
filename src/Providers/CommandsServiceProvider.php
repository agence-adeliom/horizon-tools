<?php

declare(strict_types=1);

namespace Adeliom\SageTools\Providers;

use Adeliom\SageTools\Console\Commands\ListBlocks;
use Adeliom\SageTools\Console\Commands\ListPostTypes;
use Adeliom\SageTools\Console\Commands\ListTaxonomies;
use Adeliom\SageTools\Console\Commands\MakeBlock;
use Adeliom\SageTools\Console\Commands\MakePostType;
use Adeliom\SageTools\Console\Commands\MakeTaxonomy;
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
				ListBlocks::class,
				ListPostTypes::class,
				ListTaxonomies::class,
			]);
		} catch (\Exception $e) {
			throw new SkipProviderException($e->getMessage());
		}
	}
}