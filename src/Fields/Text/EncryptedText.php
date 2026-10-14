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
     * Affiche la valeur (masquée, avec un bouton « Afficher ») dans l’administration.
     * Par défaut, la valeur enregistrée n’est jamais renvoyée au navigateur.
     */
    public function revealable(bool $revealable = true): static
    {
        $this->settings['revealable'] = $revealable;

        return $this;
    }
}
