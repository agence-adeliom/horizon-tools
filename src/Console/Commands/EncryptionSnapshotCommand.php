<?php

declare(strict_types=1);

namespace Adeliom\HorizonTools\Console\Commands;

use Adeliom\HorizonTools\Services\Encryption\EncryptedValueCodec;
use Adeliom\HorizonTools\Services\Encryption\EncryptedValueRepository;
use Adeliom\HorizonTools\Services\Encryption\EncryptionSnapshot;
use Illuminate\Console\Command;

class EncryptionSnapshotCommand extends Command
{
    protected $signature = 'encryption:snapshot {--file= : Fichier d’instantané (défaut : dossier temporaire du système, voir le chemin affiché)}';
    protected $description = 'Sauvegarde les valeurs chiffrées lisibles, à lancer avant un import de base (db:pull)';

    public function handle(EncryptedValueRepository $repository): int
    {
        $codec = EncryptedValueCodec::make();

        // Jamais bloquant : sans clé il n'y a rien de lisible à sauvegarder, et cette
        // commande tourne en amont d'un db:pull qu'elle ne doit pas empêcher.
        if ($codec === null) {
            $this->warn('APP_KEY absente : aucune valeur à sauvegarder.');

            return self::SUCCESS;
        }

        $path = $this->option('file') ?: EncryptionSnapshot::defaultPath();
        $count = EncryptionSnapshot::capture($repository, $codec, $path);
        $this->info(sprintf('%d valeur(s) chiffrée(s) sauvegardée(s) dans %s', $count, $path));

        return self::SUCCESS;
    }
}
