<?php

declare(strict_types=1);

namespace Adeliom\HorizonTools\Console\Commands;

use Adeliom\HorizonTools\Services\Encryption\EncryptedValueCodec;
use Adeliom\HorizonTools\Services\Encryption\EncryptedValueRepository;
use Adeliom\HorizonTools\Services\Encryption\EncryptionSnapshot;
use Illuminate\Console\Command;

class EncryptionRestoreCommand extends Command
{
    protected $signature = 'encryption:restore {--file= : Fichier d’instantané (défaut : dossier temporaire du système, voir le chemin affiché)}';
    protected $description = 'Remet les valeurs chiffrées sauvegardées à la place des valeurs illisibles, après un import de base (db:pull)';

    public function handle(EncryptedValueRepository $repository): int
    {
        $path = $this->option('file') ?: EncryptionSnapshot::defaultPath();

        if (!is_file($path)) {
            $this->info(sprintf('Aucun instantané (%s) : rien à restaurer.', $path));

            return self::SUCCESS;
        }

        $codec = EncryptedValueCodec::make();

        if ($codec === null) {
            $this->error('APP_KEY absente ou invalide : restauration impossible.');

            return self::FAILURE;
        }

        try {
            $result = EncryptionSnapshot::restore($repository, $codec, $path);
        } catch (\JsonException | \RuntimeException $e) {
            $this->error(
                $e instanceof \JsonException
                    ? sprintf('Instantané invalide (%s) : rien n’a été restauré.', $e->getMessage())
                    : $e->getMessage()
            );

            return self::FAILURE;
        }

        $this->info(
            sprintf(
                '%d valeur(s) restaurée(s), %d conservée(s) (déjà lisibles), %d ignorée(s).',
                count($result['restored']),
                count($result['kept']),
                count($result['skipped'])
            )
        );

        foreach ($result['skipped'] as $location) {
            $this->line('  ignorée : ' . $location);
        }

        return self::SUCCESS;
    }
}
