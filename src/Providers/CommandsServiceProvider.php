<?php

declare(strict_types=1);

namespace Adeliom\HorizonTools\Providers;

use Adeliom\HorizonTools\Console\Commands\EncryptionRestore;
use Adeliom\HorizonTools\Console\Commands\EncryptionRotate;
use Adeliom\HorizonTools\Console\Commands\EncryptionSnapshot;
use Adeliom\HorizonTools\Console\Commands\EncryptionStatus;
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
use Adeliom\HorizonTools\Console\Listeners\PreventKeyGenerate;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Support\Facades\Event;
use Roots\Acorn\Exceptions\SkipProviderException;
use Roots\Acorn\Sage\SageServiceProvider;

class CommandsServiceProvider extends SageServiceProvider
{
    public function boot(): void
    {
        // Avant les commandes : un SkipProviderException ne doit pas désactiver le blocage.
        Event::listen(CommandStarting::class, [PreventKeyGenerate::class, 'handle']);

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
                EncryptionStatus::class,
                EncryptionRotate::class,
                EncryptionSnapshot::class,
                EncryptionRestore::class,
            ]);
        } catch (\Exception $e) {
            throw new SkipProviderException($e->getMessage());
        }
    }
}
