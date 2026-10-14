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
            $this->error('APP_KEY absente ou invalide : rechiffrement impossible.');

            return self::FAILURE;
        }

        $results = ['rotated' => [], 'unreadable' => [], 'failed' => []];

        foreach ($repository->all() as $value) {
            $write = static fn(string $token): bool => $repository->write($value->table, $value->objectId, $value->key, $token);
            $results[$this->rotateToken($codec, $value->value, $write)][] = $value->location();
        }

        foreach ($repository->contentTokens() as $item) {
            $write = static fn(string $token): bool => $repository->replaceInContent($item['postId'], $item['token'], $token);
            $results[$this->rotateToken($codec, $item['token'], $write)][] = sprintf('posts:%d:post_content', $item['postId']);
        }

        foreach ($repository->embeddedOptionTokens() as $item) {
            $write = static fn(string $token): bool => $repository->replaceInOption($item['option'], $item['token'], $token);
            $results[$this->rotateToken($codec, $item['token'], $write)][] = sprintf('options:0:%s (contenu)', $item['option']);
        }

        wp_cache_flush();
        $this->info(sprintf('%d valeur(s) rechiffrée(s) avec APP_KEY.', count($results['rotated'])));

        $this->listLocations(
            $results['unreadable'],
            '%d valeur(s) illisible(s), laissée(s) en l’état (ancienne clé absente de APP_PREVIOUS_KEYS ?) :'
        );
        $this->listLocations(
            $results['failed'],
            '%d valeur(s) non rechiffrée(s), laissée(s) en l’état (écriture en échec ou clair vide) :'
        );

        if ($results['unreadable'] !== [] || $results['failed'] !== []) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * Rechiffre un jeton avec APP_KEY et le réécrit avec $write. decrypt() essaie APP_KEY puis
     * APP_PREVIOUS_KEYS. Seul un remplacement effectif compte : un jeton dont le clair est vide
     * (ou déjà préfixé) donnerait encrypt($plain) === $plain, il n’est jamais réécrit.
     *
     * @param callable(string): bool $write vrai si la valeur a bien été remplacée
     *
     * @return 'rotated'|'unreadable'|'failed'
     */
    private function rotateToken(EncryptedValueCodec $codec, string $token, callable $write): string
    {
        $plain = $codec->decrypt($token);

        if ($plain === null) {
            return 'unreadable';
        }

        $encrypted = $codec->encrypt($plain);

        return $encrypted !== $plain && $write($encrypted) ? 'rotated' : 'failed';
    }

    /**
     * @param list<string> $locations
     */
    private function listLocations(array $locations, string $message): void
    {
        if ($locations === []) {
            return;
        }

        $this->warn(sprintf($message, count($locations)));

        foreach ($locations as $location) {
            $this->line('  ' . $location);
        }
    }
}
