<?php

declare(strict_types=1);

namespace Adeliom\HorizonTools\Services;

use Illuminate\Support\Facades\Config;

/**
 * Service de gestion des tags de remplacement dans les textes.
 *
 * Les remplacements sont configurés via `config/text.php` :
 * ```php
 * 'replacements' => [
 *     'enable' => true,
 *     'values' => [
 *         'bold' => [
 *             'name' => 'Gras',
 *             'open' => '{bold}',
 *             'close' => '{/bold}',
 *             'openReplacement' => '<strong>',
 *             'closeReplacement' => '</strong>',
 *             'enabledByDefault' => true,
 *         ],
 *     ],
 * ]
 * ```
 *
 * Un tag avec `enabledByDefault => false` est masqué et non appliqué
 * sauf s'il est explicitement listé via le paramètre `$include`.
 */
class TextService
{
    public static function areTextReplacementsEnabled(): bool
    {
        return Config::get('text.replacements.enable', false);
    }

    /**
     * @return array<string, array{open: string, close: string, openReplacement: string, closeReplacement: string, name?: string, enabledByDefault?: bool}>
     */
    public static function getTextReplacements(): array
    {
        return Config::get('text.replacements.values', []);
    }

    /**
     * Détermine si un tag doit être actif pour un champ donné.
     *
     * Priorité : exclude > include > enabledByDefault (true si absent).
     *
     * @param array<string, mixed> $config
     * @param array<string>        $include
     * @param array<string>        $exclude
     */
    private static function isTagActive(string $key, array $config, array $include, array $exclude): bool
    {
        if (in_array($key, $exclude)) {
            return false;
        }

        if (in_array($key, $include)) {
            return true;
        }

        return $config['enabledByDefault'] ?? true;
    }

    /**
     * Génère le HTML d'instructions affichant les tags disponibles.
     *
     * @param array<string> $exclude Noms des tags à masquer
     * @param array<string> $include Noms des tags à ajouter même si enabledByDefault = false
     * @return string HTML avec les boutons de tags
     */
    public static function getTextReplacementInstructionsHtml(array $exclude = [], array $include = []): string
    {
        $instructions = [];

        if (self::areTextReplacementsEnabled()) {
            foreach (self::getTextReplacements() as $name => $config) {
                if (!self::isTagActive($name, $config, $include, $exclude)) {
                    continue;
                }

                if (empty($config['open']) || empty($config['close'])) {
                    continue;
                }

                $prettyName = !empty($config['name']) ? $config['name'] : $name;

                $instructions[] = sprintf(
                    '<button type="button" class="acf-text-tag button button-small" data-open="%s" data-close="%s">%s</button>',
                    esc_attr($config['open']),
                    esc_attr($config['close']),
                    esc_html($prettyName),
                );
            }
        }

        return implode(' ', $instructions);
    }

    /**
     * Applique les remplacements de tags sur une chaîne.
     *
     * Ne remplace un tag que si le nombre d'occurrences ouvrantes
     * et fermantes est identique (pour éviter les remplacements partiels).
     * Respecte la même logique d'activation que la toolbar (enabledByDefault,
     * include, exclude) pour éviter tout contournement côté rendu.
     *
     * @param string|false|null $base    Texte source
     * @param array<string>     $exclude Noms des tags à ignorer
     * @param array<string>     $include Noms des tags à activer même si enabledByDefault = false
     */
    public static function handleTextReplacements(null|false|string $base, array $exclude = [], array $include = []): string
    {
        if (empty($base)) {
            return '';
        }

        $replacedString = $base;

        foreach (self::getTextReplacements() as $key => $data) {
            if (!self::isTagActive($key, $data, $include, $exclude)) {
                continue;
            }

            $open = $data['open'] ?? null;
            $close = $data['close'] ?? null;
            $openReplacement = $data['openReplacement'] ?? null;
            $closeReplacement = $data['closeReplacement'] ?? null;

            if (!empty($open) && !empty($close) && !empty($openReplacement) && !empty($closeReplacement)) {
                if (substr_count($replacedString, $open) === substr_count($replacedString, $close)) {
                    $replacedString = str_replace([$open, $close], [$openReplacement, $closeReplacement], $replacedString);
                }
            }
        }

        return $replacedString;
    }
}
