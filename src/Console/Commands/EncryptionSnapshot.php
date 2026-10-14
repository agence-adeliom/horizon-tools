<?php

declare(strict_types=1);

namespace Adeliom\HorizonTools\Console\Commands;

use Adeliom\HorizonTools\Services\Encryption\EncryptedValueCodec;
use Adeliom\HorizonTools\Services\Encryption\EncryptedValueRepository;
use Adeliom\HorizonTools\Services\Encryption\SnapshotStore;
use Illuminate\Console\Command;

class EncryptionSnapshot extends Command
{
    protected $signature = 'encryption:snapshot {--file= : Fichier d’instantané (défaut : dossier temporaire du système, voir le chemin affiché)}';
    protected $description = 'Sauvegarde les valeurs chiffrées lisibles, à lancer avant un import de base (db:pull)';

    public function handle(EncryptedValueRepository $repository): int
    {
        $codec = EncryptedValueCodec::make();

        $path = $this->option('file') ?: SnapshotStore::defaultPath();

        // Sans clé, jamais bloquant : il n’y a rien de lisible à sauvegarder, et cette commande
        // tourne en amont d’un db:pull qu’elle ne doit pas empêcher. On écrit quand même un
        // instantané vide : un ancien fichier ne doit pas rester en place. Si cette écriture
        // échoue (dossier refusé, disque plein…), on avertit sans échouer.
        if ($codec === null) {
            try {
                SnapshotStore::capture($repository, null, $path);
                $this->warn('APP_KEY absente ou invalide : aucune valeur à sauvegarder (instantané vide écrit).');
            } catch (\Throwable $e) {
                $this->warn(
                    sprintf('APP_KEY absente ou invalide : aucune valeur à sauvegarder (instantané vide non écrit : %s).', $e->getMessage())
                );
            }

            return self::SUCCESS;
        }

        // Avec clé, un instantané non écrit est un échec : l’import qui suit perdrait les valeurs locales.
        try {
            $count = SnapshotStore::capture($repository, $codec, $path);
        } catch (\Throwable $e) {
            $this->error(sprintf('Instantané non écrit : %s', $e->getMessage()));

            return self::FAILURE;
        }

        $this->info(sprintf('%d valeur(s) chiffrée(s) sauvegardée(s) dans %s', $count, $path));

        return self::SUCCESS;
    }
}
