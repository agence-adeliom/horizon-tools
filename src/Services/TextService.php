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
 *         ],
 *     ],
 * ]
 * ```
 */
class TextService
{
    public static function areTextReplacementsEnabled(): bool
    {
        return Config::get('text.replacements.enable', false);
    }

    /**
     * @return array<string, array{open: string, close: string, openReplacement: string, closeReplacement: string, name?: string}>
     */
    public static function getTextReplacements(): array
    {
        return Config::get('text.replacements.values', []);
    }

    /**
     * Génère le HTML d'instructions affichant les tags disponibles.
     *
     * @param array<string> $exclude Noms des tags à masquer
     * @return string HTML avec les tags séparés par des <br>
     */
    public static function getTextReplacementInstructionsHtml(array $exclude = []): string
    {
        $instructions = [];

        if (self::areTextReplacementsEnabled()) {
            foreach (self::getTextReplacements() as $name => $config) {
                if (in_array($name, $exclude)) {
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
     *
     * @param string|false|null $base   Texte source
     * @param array<string>     $exclude Noms des tags à ignorer
     */
    public static function handleTextReplacements(null|false|string $base, array $exclude = []): string
    {
        if (empty($base)) {
            return '';
        }

        $replacedString = $base;

        foreach (self::getTextReplacements() as $key => $data) {
            if (in_array($key, $exclude)) {
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
