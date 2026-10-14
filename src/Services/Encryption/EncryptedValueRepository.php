<?php

declare(strict_types=1);

namespace Adeliom\HorizonTools\Services\Encryption;

/**
 * Accès aux valeurs chiffrées en base, retrouvées grâce au préfixe du codec.
 *
 * Couvre wp_options (pages d’options ACF) et les trois tables de métadonnées. Les
 * écritures passent par les API WordPress pour garder les caches cohérents. Site
 * courant uniquement en multisite.
 */
final class EncryptedValueRepository
{
    private const META_TYPES = ['postmeta' => 'post', 'termmeta' => 'term', 'usermeta' => 'user'];

    /**
     * @return list<StoredEncryptedValue>
     */
    public function all(): array
    {
        global $wpdb;

        $like = $wpdb->esc_like(EncryptedValueCodec::PREFIX) . '%';
        $values = [];

        $rows = $wpdb->get_results(
            $wpdb->prepare("SELECT option_name, option_value FROM {$wpdb->options} WHERE option_value LIKE %s", $like)
        );

        foreach ($rows as $row) {
            $values[] = new StoredEncryptedValue(
                'options',
                0,
                $row->option_name,
                $row->option_value,
                $this->read('options', 0, '_' . $row->option_name)
            );
        }

        foreach (self::META_TYPES as $table => $type) {
            $idColumn = $type . '_id';
            $rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT {$idColumn} AS object_id, meta_key, meta_value FROM {$wpdb->{$table}} WHERE meta_value LIKE %s",
                    $like
                )
            );

            foreach ($rows as $row) {
                $objectId = (int) $row->object_id;
                $values[] = new StoredEncryptedValue(
                    $table,
                    $objectId,
                    $row->meta_key,
                    $row->meta_value,
                    $this->read($table, $objectId, '_' . $row->meta_key)
                );
            }
        }

        return $values;
    }

    public function read(string $table, int $objectId, string $key): ?string
    {
        if ($table === 'options') {
            $value = get_option($key, null);

            return is_string($value) ? $value : null;
        }

        $type = $this->metaType($table);

        if (!metadata_exists($type, $objectId, $key)) {
            return null;
        }

        return (string) get_metadata($type, $objectId, $key, true);
    }

    public function write(string $table, int $objectId, string $key, string $value): void
    {
        if ($table === 'options') {
            update_option($key, $value);

            return;
        }

        // update_metadata() déséchappe sa valeur : wp_slash() la préserve à l’identique.
        update_metadata($this->metaType($table), $objectId, wp_slash($key), wp_slash($value));
    }

    public function objectExists(string $table, int $objectId): bool
    {
        return match ($table) {
            'options' => true,
            'postmeta' => get_post($objectId) !== null,
            'termmeta' => get_term($objectId) instanceof \WP_Term,
            'usermeta' => get_userdata($objectId) !== false,
            default => throw new \InvalidArgumentException(sprintf('Table inconnue : %s', $table)),
        };
    }

    private function metaType(string $table): string
    {
        return self::META_TYPES[$table] ?? throw new \InvalidArgumentException(sprintf('Table inconnue : %s', $table));
    }
}
