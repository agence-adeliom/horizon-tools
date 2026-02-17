<?php

declare(strict_types=1);

namespace Adeliom\HorizonTools\Fields\Text;

use Extended\ACF\Fields\Text;

/**
 * Champ Extended ACF "texte avec tags".
 *
 * Wrapper pour le type ACF `text_with_tags`, permettant
 * de l'utiliser avec la syntaxe fluide de vinkla/extended-acf.
 */
class TextWithTags extends Text
{
    protected ?string $type = 'text_with_tags';

    public static function make(string $label, ?string $name = null): static
    {
        return parent::make($label, $name);
    }

    /**
     * Exclut certains tags du remplacement automatique.
     *
     * @param string|array<string> $exclude Noms des tags à exclure
     */
    public function exclude(string|array $exclude = []): self
    {
        if (is_string($exclude)) {
            $exclude = [$exclude];
        }

        $this->settings['exclude'] = $exclude;

        return $this;
    }
}
