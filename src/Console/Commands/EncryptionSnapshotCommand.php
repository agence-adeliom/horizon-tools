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

        $path = $this->option('file') ?: EncryptionSnapshot::defaultPath();

        // Jamais bloquant : sans clé il n'y a rien de lisible à sauvegarder, et cette
        // commande tourne en amont d'un db:pull qu'elle ne doit pas empêcher. On écrit
        // quand même un instantané vide : un ancien fichier ne doit pas rester en place.
        // Si l’écriture échoue (dossier refusé, disque plein…), on avertit sans échouer.
        if ($codec === null) {
            try {
                EncryptionSnapshot::capture($repository, null, $path);
                $this->warn('APP_KEY absente : aucune valeur à sauvegarder (instantané vide écrit).');
            } catch (\Throwable $e) {
                $this->warn(sprintf('APP_KEY absente : aucune valeur à sauvegarder (instantané vide non écrit : %s).', $e->getMessage()));
            }

            return self::SUCCESS;
        }

        $count = EncryptionSnapshot::capture($repository, $codec, $path);
        $this->info(sprintf('%d valeur(s) chiffrée(s) sauvegardée(s) dans %s', $count, $path));

        return self::SUCCESS;
    }
}
