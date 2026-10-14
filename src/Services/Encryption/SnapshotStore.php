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
final class SnapshotStore
{
    private const VERSION = 1;

    public static function defaultPath(): string
    {
        return sys_get_temp_dir() . '/horizon-tools-' . substr(md5(ABSPATH), 0, 12) . '/encryption-snapshot.json';
    }

    /**
     * Ne capture que les valeurs entières de all() (options, métadonnées). Les jetons embarqués
     * dans un texte (blocs ACF de post_content, révisions comprises, et options comme
     * widget_block) sont exclus : le contenu des pages voyage avec la base importée et ses
     * identifiants ne sont pas stables d’un environnement à l’autre.
     */
    public static function capture(EncryptedValueRepository $repository, ?EncryptedValueCodec $codec, string $path): int
    {
        // Sans clé : instantané vide, pour ne jamais laisser en place un ancien fichier périmé.
        $values =
            $codec === null
                ? []
                : array_values(
                    array_filter(
                        $repository->all(),
                        static fn(StoredEncryptedValue $value): bool => EncryptedValueCodec::isEncrypted($value->value) &&
                            $codec->decrypt($value->value) !== null
                    )
                );

        $directory = dirname($path);

        // Le dossier par défaut est dans un /tmp partagé : on refuse tout ce qui n’est pas à nous.
        if (is_link($directory) || (file_exists($directory) && !is_dir($directory))) {
            throw new \RuntimeException(sprintf('Dossier d’instantané refusé (lien symbolique ou non-dossier) : %s', $directory));
        }

        if (is_dir($directory)) {
            if (fileowner($directory) !== self::currentUserId()) {
                throw new \RuntimeException(sprintf('Dossier d’instantané refusé (propriétaire différent) : %s', $directory));
            }

            // Les droits d’un dossier existant choisi par l’utilisateur (--file) ne sont jamais modifiés :
            // seul le dossier par défaut, qui est à nous, est resserré.
            if ($directory === dirname(self::defaultPath())) {
                chmod($directory, 0700);
            }
        } elseif (!mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new \RuntimeException(sprintf('Impossible de créer le dossier %s', $directory));
        }

        if (is_link($path)) {
            throw new \RuntimeException(sprintf('Fichier d’instantané refusé (lien symbolique) : %s', $path));
        }

        $json = json_encode(
            [
                'version' => self::VERSION,
                'createdAt' => gmdate('c'),
                'values' => array_map(static fn(StoredEncryptedValue $value): array => $value->toArray(), $values),
            ],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );

        $previousUmask = umask(0077);

        try {
            $written = file_put_contents($path, $json, LOCK_EX);
        } finally {
            umask($previousUmask);
        }

        if ($written === false) {
            throw new \RuntimeException(sprintf('Impossible d’écrire %s', $path));
        }

        chmod($path, 0600);

        return count($values);
    }

    /**
     * L’instantané entier est validé avant la première écriture en base.
     *
     * @return array{restored: list<string>, kept: list<string>, skipped: list<string>, failed: list<string>}
     *         failed : écriture en échec, valeur laissée en l’état
     *
     * @throws \RuntimeException instantané illisible ou invalide, JSON mal formé compris (rien n’a été restauré)
     */
    public static function restore(EncryptedValueRepository $repository, EncryptedValueCodec $codec, string $path): array
    {
        // Le cache d’objets peut contenir des valeurs d’avant l’import de base.
        wp_cache_flush();

        if (is_link($path)) {
            throw new \RuntimeException(sprintf('Fichier d’instantané refusé (lien symbolique) : %s', $path));
        }

        $contents = is_file($path) ? file_get_contents($path) : false;

        if ($contents === false) {
            throw new \RuntimeException(sprintf('Instantané illisible (%s) : rien n’a été restauré.', $path));
        }

        try {
            $data = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw self::invalid($path, $e->getMessage(), $e);
        }

        $entries = self::validate($data, $path);
        $result = ['restored' => [], 'kept' => [], 'skipped' => [], 'failed' => []];

        foreach ($entries as $saved) {
            if ($codec->decrypt($saved->value) === null) {
                $result['skipped'][] = $saved->location();

                continue;
            }

            $current = $repository->read($saved->table, $saved->objectId, $saved->key);

            // Valeur lisible (ou en clair) : celle de la base importée prime.
            if ($current !== null && $current !== '' && !$codec->isUnreadableToken($current)) {
                $result['kept'][] = $saved->location();

                continue;
            }

            // Métadonnées : les ID de posts, termes et utilisateurs ne sont pas stables d’un environnement
            // à l’autre. On n’écrit que par-dessus une valeur chiffrée illisible, seule preuve que la ligne
            // portait bien ce champ chiffré ; une ligne absente ou vide n’est jamais touchée (elle pourrait
            // viser un autre objet, ou avoir été vidée volontairement).
            // Options : sans identifiant d’objet, une valeur absente ou vide reste recréable.
            if ($saved->table !== 'options' && ($current === null || !$codec->isUnreadableToken($current))) {
                $result['skipped'][] = $saved->location();

                continue;
            }

            if (!$repository->write($saved->table, $saved->objectId, $saved->key, $saved->value)) {
                $result['failed'][] = $saved->location();

                continue;
            }

            $referenceKey = '_' . $saved->key;

            if ($saved->reference !== null && $repository->read($saved->table, $saved->objectId, $referenceKey) === null) {
                $repository->write($saved->table, $saved->objectId, $referenceKey, $saved->reference);
            }

            $result['restored'][] = $saved->location();
        }

        wp_cache_flush();

        return $result;
    }

    /**
     * @return list<StoredEncryptedValue>
     */
    private static function validate(mixed $data, string $path): array
    {
        if (!is_array($data) || ($data['version'] ?? null) !== self::VERSION) {
            throw self::invalid($path, 'version non prise en charge');
        }

        if (!isset($data['values']) || !is_array($data['values']) || !array_is_list($data['values'])) {
            throw self::invalid($path, 'liste « values » absente');
        }

        $entries = [];

        foreach ($data['values'] as $index => $entry) {
            if (!is_array($entry)) {
                throw self::invalid($path, sprintf('entrée %d non valide', $index));
            }

            if (!in_array($entry['table'] ?? null, EncryptedValueRepository::TABLES, true)) {
                throw self::invalid($path, sprintf('entrée %d : table inconnue', $index));
            }

            if (!is_int($entry['objectId'] ?? null) || $entry['objectId'] < 0) {
                throw self::invalid($path, sprintf('entrée %d : objectId invalide', $index));
            }

            if (!is_string($entry['key'] ?? null) || $entry['key'] === '') {
                throw self::invalid($path, sprintf('entrée %d : clé invalide', $index));
            }

            if (!EncryptedValueCodec::isEncrypted($entry['value'] ?? null)) {
                throw self::invalid($path, sprintf('entrée %d : valeur non chiffrée', $index));
            }

            if (($entry['reference'] ?? null) !== null && !is_string($entry['reference'])) {
                throw self::invalid($path, sprintf('entrée %d : référence invalide', $index));
            }

            $entries[] = StoredEncryptedValue::fromArray($entry);
        }

        return $entries;
    }

    /** Erreur commune aux instantanés invalides (JSON mal formé ou contenu refusé par validate()). */
    private static function invalid(string $path, string $reason, ?\Throwable $previous = null): \RuntimeException
    {
        return new \RuntimeException(sprintf('Instantané invalide (%s : %s) : rien n’a été restauré.', $path, $reason), 0, $previous);
    }

    private static function currentUserId(): int
    {
        return function_exists('posix_geteuid') ? posix_geteuid() : getmyuid();
    }
}
