<?php

declare(strict_types=1);

namespace Adeliom\HorizonTools\Console\Listeners;

use Adeliom\HorizonTools\Services\Encryption\EncryptedValueCodec;
use Adeliom\HorizonTools\Services\Encryption\EncryptedValueRepository;
use Adeliom\HorizonTools\Services\Encryption\StoredEncryptedValue;
use Illuminate\Console\Events\CommandStarting;
use Symfony\Component\Console\Exception\RuntimeException;

/**
 * Refuse `key:generate` (sans `--show`) tant que des valeurs chiffrées lisibles existent :
 * la nouvelle clé les rendrait illisibles.
 *
 * L’exception levée dans ce listener CommandStarting interrompt la commande avant son
 * exécution (Symfony Application::doRunCommand) ; le noyau console d’Acorn l’affiche et
 * renvoie le code 1 (Kernel::handle), que WP-CLI transmet (Bootable::bootWpCli, WP_CLI::halt).
 */
final class PreventKeyGenerate
{
    public function __construct(private readonly EncryptedValueRepository $repository)
    {
    }

    public function handle(CommandStarting $event): void
    {
        if ($event->command !== 'key:generate' || $event->input->hasParameterOption('--show')) {
            return;
        }

        $codec = EncryptedValueCodec::make();

        if ($codec === null) {
            return;
        }

        $readable = count(
            array_filter($this->repository->all(), static fn(StoredEncryptedValue $value): bool => $codec->decrypt($value->value) !== null)
        );

        // Jetons embarqués : blocs ACF de post_content (révisions comprises) et options comme widget_block.
        $readable += count(
            array_filter(
                [...$this->repository->contentTokens(), ...$this->repository->embeddedOptionTokens()],
                static fn(array $item): bool => $codec->decrypt($item['token']) !== null
            )
        );

        if ($readable === 0) {
            return;
        }

        throw new RuntimeException(
            sprintf(
                "key:generate refusé : %d valeur(s) chiffrée(s) avec la clé actuelle deviendraient illisibles.\n" .
                    'Pour changer de clé : ancienne clé dans APP_PREVIOUS_KEYS, nouvelle dans APP_KEY (`wp acorn key:generate --show` pour en générer une), puis `wp acorn encryption:rotate`.',
                $readable
            )
        );
    }
}
