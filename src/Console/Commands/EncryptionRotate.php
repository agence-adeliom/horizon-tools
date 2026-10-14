<?php

declare(strict_types=1);

namespace Adeliom\HorizonTools\Console\Commands;

use Adeliom\HorizonTools\Services\Encryption\EncryptedValueCodec;
use Adeliom\HorizonTools\Services\Encryption\EncryptedValueRepository;
use Illuminate\Console\Command;

/**
 * Rechiffre toutes les valeurs avec APP_KEY.
 *
 * Procédure : déplacer l’ancienne clé dans APP_PREVIOUS_KEYS, poser la nouvelle
 * dans APP_KEY, lancer cette commande, puis retirer APP_PREVIOUS_KEYS.
 */
class EncryptionRotate extends Command
{
    protected $signature = 'encryption:rotate';
    protected $description = 'Rechiffre les valeurs chiffrées avec la clé courante (APP_KEY), après un changement de clé';

    public function handle(EncryptedValueRepository $repository): int
    {
        $codec = EncryptedValueCodec::make();

        if ($codec === null) {
            $this->error('APP_KEY absente ou invalide.');

            return self::FAILURE;
        }

        $rotated = 0;
        $unreadable = [];

        foreach ($repository->all() as $value) {
            // decrypt() essaie APP_KEY puis APP_PREVIOUS_KEYS ; encrypt() utilise toujours APP_KEY.
            $plain = $codec->decrypt($value->value);

            if ($plain === null) {
                $unreadable[] = $value->location();

                continue;
            }

            $repository->write($value->table, $value->objectId, $value->key, $codec->encrypt($plain));
            $rotated++;
        }

        foreach ($repository->contentTokens() as $item) {
            $plain = $codec->decrypt($item['token']);

            if ($plain === null) {
                $unreadable[] = sprintf('posts:%d:post_content', $item['postId']);

                continue;
            }

            $repository->replaceInContent($item['postId'], $item['token'], $codec->encrypt($plain));
            $rotated++;
        }

        foreach ($repository->embeddedOptionTokens() as $item) {
            $plain = $codec->decrypt($item['token']);

            if ($plain === null) {
                $unreadable[] = sprintf('options:0:%s (contenu)', $item['option']);

                continue;
            }

            $repository->replaceInOption($item['option'], $item['token'], $codec->encrypt($plain));
            $rotated++;
        }

        wp_cache_flush();
        $this->info(sprintf('%d valeur(s) rechiffrée(s) avec APP_KEY.', $rotated));

        if ($unreadable !== []) {
            $this->warn(
                sprintf(
                    '%d valeur(s) illisible(s), laissée(s) en l’état (ancienne clé absente de APP_PREVIOUS_KEYS ?) :',
                    count($unreadable)
                )
            );

            foreach ($unreadable as $location) {
                $this->line('  ' . $location);
            }

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
