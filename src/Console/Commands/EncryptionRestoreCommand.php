<?php

declare(strict_types=1);

namespace Adeliom\HorizonTools\Console\Commands;

use Adeliom\HorizonTools\Services\Encryption\EncryptedValueCodec;
use Adeliom\HorizonTools\Services\Encryption\EncryptedValueRepository;
use Adeliom\HorizonTools\Services\Encryption\EncryptionSnapshot;
use Illuminate\Console\Command;

class EncryptionRestoreCommand extends Command
{
    protected $signature = 'encryption:restore {--path= : Fichier d’instantané (défaut : storage/app/horizon-tools/encryption-snapshot.json)}';
    protected $description = 'Remet les valeurs chiffrées sauvegardées à la place des valeurs illisibles, après un import de base (db:pull)';

    public function handle(EncryptedValueRepository $repository): int
    {
        $path = $this->option('path') ?: EncryptionSnapshot::defaultPath();

        if (!is_file($path)) {
            $this->info(sprintf('Aucun instantané (%s) : rien à restaurer.', $path));

            return self::SUCCESS;
        }

        $codec = EncryptedValueCodec::make();

        if ($codec === null) {
            $this->error('APP_KEY absente ou invalide : restauration impossible.');

            return self::FAILURE;
        }

        $result = EncryptionSnapshot::restore($repository, $codec, $path);

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
