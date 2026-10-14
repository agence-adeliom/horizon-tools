<?php

declare(strict_types=1);

namespace Adeliom\HorizonTools\Fields\Text;

use Extended\ACF\Fields\Field;
use Extended\ACF\Fields\Settings\ConditionalLogic;
use Extended\ACF\Fields\Settings\HelperText;
use Extended\ACF\Fields\Settings\Placeholder;
use Extended\ACF\Fields\Settings\Required;
use Extended\ACF\Fields\Settings\Wrapper;

/**
 * Champ Extended ACF « texte chiffré » (clés API, jetons, licences).
 *
 * Wrapper du type ACF `encrypted_text` : valeur chiffrée en base avec APP_KEY,
 * get_field() renvoie le clair. Utilisable dans les pages d’options, types de
 * contenu, taxonomies et profils — pas dans les blocs.
 */
class EncryptedText extends Field
{
    use ConditionalLogic;
    use HelperText;
    use Placeholder;
    use Required;
    use Wrapper;

    protected ?string $type = 'encrypted_text';

    /**
     * Par défaut, la valeur est affichable dans l’administration (masquée, avec un bouton « Afficher »).
     * `writeOnly()` ne la renvoie jamais au navigateur : champ en écriture seule, vide = conserver.
     * Sans effet dans un bloc, où ce champ est indisponible.
     */
    public function writeOnly(bool $writeOnly = true): static
    {
        $this->settings['revealable'] = !$writeOnly;

        return $this;
    }
}
