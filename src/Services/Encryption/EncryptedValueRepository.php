<?php

declare(strict_types=1);

namespace Adeliom\HorizonTools\Services\Encryption;

/**
 * Accès aux valeurs chiffrées en base, retrouvées grâce au préfixe du codec.
 *
 * Couvre wp_options (pages d’options ACF) et les trois tables de métadonnées. Les
 * écritures passent par les API WordPress pour garder les caches cohérents. Site
 * courant uniquement en multisite.
 *
 * Les jetons « embarqués » au milieu d’un texte ne sont pas des valeurs entières et ne
 * sont pas renvoyés par all() : blocs ACF dans post_content (révisions comprises, voir
 * contentTokens()) et options dont le jeton n’est pas au début de la valeur, comme
 * widget_block (voir embeddedOptionTokens()).
 */
final class EncryptedValueRepository
{
    /** Tables couvertes : wp_options et les trois tables de métadonnées. */
    public const TABLES = ['options', 'postmeta', 'termmeta', 'usermeta'];

    /**
     * Un jeton chiffré au milieu d’un texte (base64 : ni `-` ni caractère échappé par JSON).
     * Le préfixe du codec n’a aucun caractère spécial d’expression régulière.
     */
    public const TOKEN_PATTERN = '/' . EncryptedValueCodec::PREFIX . '[A-Za-z0-9+\/=]+/';

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

    /**
     * Jetons chiffrés présents dans post_content : tous types et statuts, révisions comprises.
     * Un jeton n’est listé qu’une fois par contenu.
     *
     * @return list<array{postId: int, token: string}>
     */
    public function contentTokens(): array
    {
        global $wpdb;

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT ID, post_content FROM {$wpdb->posts} WHERE post_content LIKE %s",
                '%' . $wpdb->esc_like(EncryptedValueCodec::PREFIX) . '%'
            )
        );

        $tokens = [];

        foreach ($rows as $row) {
            foreach ($this->extractTokens((string) $row->post_content) as $token) {
                $tokens[] = ['postId' => (int) $row->ID, 'token' => $token];
            }
        }

        return $tokens;
    }

    /**
     * Remplace exactement un jeton dans post_content. $wpdb->update() évite wp_update_post() :
     * ni révision ni hook. Le reste du contenu n’est pas touché.
     *
     * @return bool vrai si le contenu a été modifié
     */
    public function replaceInContent(int $postId, string $oldToken, string $newToken): bool
    {
        global $wpdb;

        $content = $wpdb->get_var($wpdb->prepare("SELECT post_content FROM {$wpdb->posts} WHERE ID = %d", $postId));

        if (!is_string($content)) {
            return false;
        }

        $replaced = $this->replaceToken($content, $oldToken, $newToken);

        if ($replaced === $content) {
            return false;
        }

        $updated = $wpdb->update($wpdb->posts, ['post_content' => $replaced], ['ID' => $postId]);
        clean_post_cache($postId);

        return $updated !== false;
    }

    /**
     * Jetons embarqués dans des options dont la valeur ne commence pas par le préfixe
     * (ex. widget_block : tableau sérialisé dont les `content` contiennent des blocs ACF).
     * L’option est lue avec get_option() et ses chaînes sont parcourues récursivement : la
     * valeur sérialisée brute n’est jamais analysée.
     *
     * @return list<array{option: string, token: string}>
     */
    public function embeddedOptionTokens(): array
    {
        global $wpdb;

        $prefix = $wpdb->esc_like(EncryptedValueCodec::PREFIX);
        $names = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT option_name FROM {$wpdb->options} WHERE option_value LIKE %s AND option_value NOT LIKE %s",
                '%' . $prefix . '%',
                $prefix . '%'
            )
        );

        $tokens = [];

        foreach ($names as $name) {
            $found = [];
            $this->collectTokens(get_option($name), $found);

            foreach (array_unique($found) as $token) {
                $tokens[] = ['option' => (string) $name, 'token' => $token];
            }
        }

        return $tokens;
    }

    /**
     * Remplace exactement un jeton dans une option embarquée : lecture désérialisée, remplacement
     * dans les chaînes, puis update_option(). Jamais de remplacement dans la chaîne sérialisée
     * (les longueurs `s:N:` ne seraient plus justes).
     *
     * @return bool vrai si l’option a été modifiée
     */
    public function replaceInOption(string $name, string $oldToken, string $newToken): bool
    {
        $value = get_option($name, null);

        if ($value === null) {
            return false;
        }

        $changed = false;
        $replaced = $this->mapStrings($value, function (string $text) use ($oldToken, $newToken, &$changed): string {
            $result = $this->replaceToken($text, $oldToken, $newToken);
            $changed = $changed || $result !== $text;

            return $result;
        });

        return $changed && update_option($name, $replaced);
    }

    /**
     * @return list<string>
     */
    private function extractTokens(string $text): array
    {
        preg_match_all(self::TOKEN_PATTERN, $text, $matches);

        return array_values(array_unique($matches[0]));
    }

    /** Remplace les occurrences exactes d’un jeton (un jeton plus long qui le prolonge n’est pas touché). */
    private function replaceToken(string $text, string $oldToken, string $newToken): string
    {
        return preg_replace_callback(
            self::TOKEN_PATTERN,
            static fn(array $match): string => $match[0] === $oldToken ? $newToken : $match[0],
            $text
        ) ?? $text;
    }

    /**
     * @param list<string> $found
     */
    private function collectTokens(mixed $value, array &$found): void
    {
        $this->mapStrings($value, function (string $text) use (&$found): string {
            array_push($found, ...$this->extractTokens($text));

            return $text;
        });
    }

    /** Applique $callback à toutes les chaînes d’une structure (tableaux et objets parcourus récursivement). */
    private function mapStrings(mixed $value, callable $callback): mixed
    {
        if (is_string($value)) {
            return $callback($value);
        }

        if (is_array($value)) {
            return array_map(fn(mixed $item): mixed => $this->mapStrings($item, $callback), $value);
        }

        if (is_object($value)) {
            foreach (get_object_vars($value) as $property => $item) {
                $value->{$property} = $this->mapStrings($item, $callback);
            }
        }

        return $value;
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

    /**
     * @return bool vrai si la valeur a été écrite (faux en cas d’échec, ou si elle était déjà identique)
     */
    public function write(string $table, int $objectId, string $key, string $value): bool
    {
        if ($table === 'options') {
            return update_option($key, $value);
        }

        // update_metadata() déséchappe sa valeur : wp_slash() la préserve à l’identique.
        return update_metadata($this->metaType($table), $objectId, wp_slash($key), wp_slash($value)) !== false;
    }

    private function metaType(string $table): string
    {
        return self::META_TYPES[$table] ?? throw new \InvalidArgumentException(sprintf('Table inconnue : %s', $table));
    }
}
