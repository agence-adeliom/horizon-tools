<?php

declare(strict_types=1);

namespace Adeliom\HorizonTools\Console\Commands;

use Adeliom\HorizonTools\Services\Encryption\EncryptedValueCodec;
use Adeliom\HorizonTools\Services\Encryption\EncryptedValueRepository;
use Illuminate\Console\Command;

class EncryptionStatus extends Command
{
    protected $signature = 'encryption:status';
    protected $description = 'Vérifie la clé de chiffrement et liste les valeurs chiffrées (lisibles ou non)';

    public function handle(EncryptedValueRepository $repository): int
    {
        $codec = EncryptedValueCodec::make();

        if ($codec === null) {
            $this->error('APP_KEY absente ou invalide : générer une clé avec `wp acorn key:generate`.');

            return self::FAILURE;
        }

        $rows = [];
        // Valeurs entières (options, métadonnées) : couvertes par encryption:restore.
        $unreadable = 0;
        // Jetons embarqués (post_content, options « (contenu) ») : hors instantané.
        $unreadableEmbedded = 0;

        foreach ($repository->all() as $value) {
            $readable = !$codec->isUnreadableToken($value->value);
            $unreadable += $readable ? 0 : 1;
            $rows[] = [$value->table, $value->objectId ?: '—', $value->key, $readable ? 'lisible' : 'ILLISIBLE'];
        }

        foreach ($repository->contentTokens() as $item) {
            $readable = !$codec->isUnreadableToken($item['token']);
            $unreadableEmbedded += $readable ? 0 : 1;
            $rows[] = ['posts', $item['postId'], 'post_content', $readable ? 'lisible' : 'ILLISIBLE'];
        }

        foreach ($repository->embeddedOptionTokens() as $item) {
            $readable = !$codec->isUnreadableToken($item['token']);
            $unreadableEmbedded += $readable ? 0 : 1;
            $rows[] = ['options', '—', $item['option'] . ' (contenu)', $readable ? 'lisible' : 'ILLISIBLE'];
        }

        if ($rows === []) {
            $this->info('Clé présente. Aucune valeur chiffrée en base.');

            return self::SUCCESS;
        }

        $this->table(['Table', 'Objet', 'Clé', 'État'], $rows);
        $this->line(
            sprintf('%d valeur(s) chiffrée(s), dont %d illisible(s) avec la clé courante.', count($rows), $unreadable + $unreadableEmbedded)
        );

        if ($unreadable > 0) {
            $this->warn(
                'Valeurs illisibles (options, métadonnées) : chiffrées avec une autre clé (ex. après un db:pull). Les ressaisir, ou lancer `wp acorn encryption:restore` si un instantané local existe.'
            );
        }

        if ($unreadableEmbedded > 0) {
            $this->warn(
                'Valeurs illisibles dans des contenus (posts, options « (contenu) ») : hors instantané. Les ressaisir (réenregistrer le bloc ou le widget), ou remettre l’ancienne clé dans APP_PREVIOUS_KEYS.'
            );
        }

        return self::SUCCESS;
    }
}
