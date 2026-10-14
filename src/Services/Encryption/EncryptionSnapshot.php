<?php

declare(strict_types=1);

namespace Adeliom\HorizonTools\Services\Encryption;

/**
 * Sauvegarde / restauration des valeurs chiffrées locales autour d'un import de base.
 *
 * Cas visé : une clé par environnement. Après un db:pull, les valeurs de la prod sont
 * illisibles en local ; on remet à leur place les valeurs locales sauvegardées juste
 * avant l'import. L'instantané ne contient que des valeurs chiffrées.
 *
 * Le chemin par défaut est dans le dossier temporaire du système : hors de toute racine web
 * et de tout dépôt. Le hash d'ABSPATH évite les collisions entre projets d'un même serveur.
 * L'instantané ne doit vivre que le temps d'un import.
 */
final class EncryptionSnapshot
{
    private const VERSION = 1;

    public static function defaultPath(): string
    {
        return sys_get_temp_dir() . '/horizon-tools-' . substr(md5(ABSPATH), 0, 12) . '/encryption-snapshot.json';
    }

    public static function capture(EncryptedValueRepository $repository, EncryptedValueCodec $codec, string $path): int
    {
        $values = array_values(
            array_filter($repository->all(), static fn(StoredEncryptedValue $value): bool => $codec->decrypt($value->value) !== null)
        );

        $directory = dirname($path);

        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new \RuntimeException(sprintf('Impossible de créer le dossier %s', $directory));
        }

        $json = json_encode(
            [
                'version' => self::VERSION,
                'createdAt' => gmdate('c'),
                'values' => array_map(static fn(StoredEncryptedValue $value): array => $value->toArray(), $values),
            ],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );

        if (file_put_contents($path, $json, LOCK_EX) === false) {
            throw new \RuntimeException(sprintf('Impossible d’écrire %s', $path));
        }

        chmod($path, 0600);

        return count($values);
    }

    /**
     * @return array{restored: list<string>, kept: list<string>, skipped: list<string>}
     */
    public static function restore(EncryptedValueRepository $repository, EncryptedValueCodec $codec, string $path): array
    {
        $result = ['restored' => [], 'kept' => [], 'skipped' => []];
        $data = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

        if (($data['version'] ?? null) !== self::VERSION) {
            throw new \RuntimeException(sprintf('Version d’instantané non prise en charge dans %s', $path));
        }

        foreach ($data['values'] as $entry) {
            $saved = StoredEncryptedValue::fromArray($entry);

            if ($codec->decrypt($saved->value) === null) {
                $result['skipped'][] = $saved->location();

                continue;
            }

            $current = $repository->read($saved->table, $saved->objectId, $saved->key);

            if ($current !== null && $current !== '' && $codec->decrypt($current) !== null) {
                $result['kept'][] = $saved->location();

                continue;
            }

            if (!$repository->objectExists($saved->table, $saved->objectId)) {
                $result['skipped'][] = $saved->location();

                continue;
            }

            if ($current === $saved->value) {
                continue;
            }

            $repository->write($saved->table, $saved->objectId, $saved->key, $saved->value);

            $referenceKey = '_' . $saved->key;

            if ($saved->reference !== null && $repository->read($saved->table, $saved->objectId, $referenceKey) === null) {
                $repository->write($saved->table, $saved->objectId, $referenceKey, $saved->reference);
            }

            $result['restored'][] = $saved->location();
        }

        wp_cache_flush();

        return $result;
    }
}
