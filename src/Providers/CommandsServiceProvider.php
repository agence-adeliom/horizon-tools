<?php

declare(strict_types=1);

namespace Adeliom\HorizonTools\Providers;

use Adeliom\HorizonTools\Console\Commands\ListBlocks;
use Adeliom\HorizonTools\Console\Commands\ListPostTypes;
use Adeliom\HorizonTools\Console\Commands\ListTaxonomies;
use Adeliom\HorizonTools\Console\Commands\MakeAdmin;
use Adeliom\HorizonTools\Console\Commands\MakeBlock;
use Adeliom\HorizonTools\Console\Commands\MakeHook;
use Adeliom\HorizonTools\Console\Commands\MakePostType;
use Adeliom\HorizonTools\Console\Commands\MakeRepository;
use Adeliom\HorizonTools\Console\Commands\MakeTaxonomy;
use Adeliom\HorizonTools\Console\Commands\MakeTemplate;
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
                MakeTemplate::class,
                MakeAdmin::class,
                MakeRepository::class,
                MakeHook::class,
                ListBlocks::class,
                ListPostTypes::class,
                ListTaxonomies::class,
            ]);
        } catch (\Exception $e) {
            throw new SkipProviderException($e->getMessage());
        }
    }
}
