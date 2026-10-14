<?php

declare(strict_types=1);

namespace Adeliom\HorizonTools\Providers;

use Adeliom\HorizonTools\Console\Commands\EncryptionRotate;
use Adeliom\HorizonTools\Console\Commands\EncryptionRestoreCommand;
use Adeliom\HorizonTools\Console\Commands\EncryptionSnapshotCommand;
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
use Adeliom\HorizonTools\Services\Encryption\EncryptedValueCodec;
use Adeliom\HorizonTools\Services\Encryption\EncryptedValueRepository;
use Adeliom\HorizonTools\Services\Encryption\StoredEncryptedValue;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Support\Facades\Event;
use Roots\Acorn\Exceptions\SkipProviderException;
use Roots\Acorn\Sage\SageServiceProvider;

class CommandsServiceProvider extends SageServiceProvider
{
    public function boot(): void
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
                EncryptionStatus::class,
                EncryptionRotate::class,
                EncryptionSnapshotCommand::class,
                EncryptionRestoreCommand::class,
            ]);
        } catch (\Exception $e) {
            throw new SkipProviderException($e->getMessage());
        }

        Event::listen(CommandStarting::class, static function (CommandStarting $event): void {
            if ($event->command !== 'key:generate' || $event->input->hasParameterOption('--show')) {
                return;
            }

            $codec = EncryptedValueCodec::make();

            if ($codec === null) {
                return;
            }

            $repository = app(EncryptedValueRepository::class);
            $readable = array_filter(
                $repository->all(),
                static fn(StoredEncryptedValue $value): bool => $codec->decrypt($value->value) !== null
            );

            // Jetons embarqués : blocs ACF de post_content (révisions comprises) et options comme widget_block.
            $embedded = array_filter(
                [...$repository->contentTokens(), ...$repository->embeddedOptionTokens()],
                static fn(array $item): bool => $codec->decrypt($item['token']) !== null
            );
            $readable = [...$readable, ...$embedded];

            if ($readable === []) {
                return;
            }

            $event->output->writeln(
                sprintf(
                    '<error>key:generate refusé : %d valeur(s) chiffrée(s) avec la clé actuelle deviendraient illisibles.</error>',
                    count($readable)
                )
            );
            $event->output->writeln(
                'Pour changer de clé : ancienne clé dans APP_PREVIOUS_KEYS, nouvelle dans APP_KEY (`wp acorn key:generate --show` pour en générer une), puis `wp acorn encryption:rotate`.'
            );

            exit(1);
        });
    }
}
