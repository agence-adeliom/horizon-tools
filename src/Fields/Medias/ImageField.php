<?php

declare(strict_types=1);

namespace Adeliom\HorizonTools\Fields\Medias;

use Adeliom\HorizonTools\Fields\Choices\TrueFalseField;
use Extended\ACF\ConditionalLogic;
use Extended\ACF\Fields\Image;
use Extended\ACF\Fields\Text;
use Illuminate\Support\Facades\Config;

class ImageField extends Image
{
    final public const IMAGE = 'image';
    final public const INFORMATIVE_SUFFIX = 'Informative';
    final public const ALT_SUFFIX = 'Alt';
    final public const GALLERY_INFORMATIVE = 'informativeImages';
    final public const CONFIG_ACCESSIBILITY = 'medias.accessibility.images';

    public static function make(string $label = 'Image', ?string $name = self::IMAGE): static
    {
        return parent::make(__($label, 'horizon-tools'), $name)->library('all')->format('array');
    }

    public function ratio(?int $width = null, ?int $height = null): static
    {
        if (null !== $width && null !== $height) {
            $this->settings['instructions'] = sprintf(__('Ratio recommandé : %dx%dpx', 'horizon-tools'), $width, $height);
        } elseif (null !== $width) {
            $this->settings['instructions'] = sprintf(__('Largeur recommandée : %dpx', 'horizon-tools'), $width);
        } elseif (null !== $height) {
            $this->settings['instructions'] = sprintf(__('Hauteur recommandée : %dpx', 'horizon-tools'), $height);
        }
        return $this;
    }

    /**
     * Champs à placer juste après le champ image `$imageName`, dans le même groupe ou répéteur 
     *
     * @param array{0: string, 1: string, 2: mixed}|null $onlyWhen Condition d'affichage supplémentaire, appliquée aux
     *                                                              deux champs (ex. ['type', '==', 'image'])
     */
    public static function accessibilityFields(string $imageName = self::IMAGE, ?array $onlyWhen = null): array
    {
        if (!self::accessibilityEnabled()) {
            return [];
        }

        $informativeName = self::informativeName($imageName);

        $informative = TrueFalseField::make(__('Image informative', 'horizon-tools'), $informativeName)
            ->helperText(__("À cocher si l'image apporte une information absente du texte qui l'accompagne (schéma, chiffre, produit, texte dans l'image) : son texte alternatif de la médiathèque est alors lu. Sinon, elle est ignorée par les lecteurs d'écran.", 'horizon-tools'))
            ->default(false);

        $altCondition = ConditionalLogic::where($informativeName, '==', 1);

        if ($onlyWhen) {
            $informative->conditionalLogic([ConditionalLogic::where(...$onlyWhen)]);
            $altCondition->and(...$onlyWhen);
        }

        $alt = Text::make(__('Texte alternatif', 'horizon-tools'), self::altName($imageName))
            ->helperText(__("Facultatif. Par défaut, le texte alternatif de l'image dans la médiathèque est utilisé. À renseigner pour le remplacer dans ce bloc : décrire l'information portée par l'image dans ce contexte, pas son apparence.", 'horizon-tools'))
            ->conditionalLogic([$altCondition]);

        return [$informative, $alt];
    }

    /**
     * Case unique pour un champ galerie (pas de réglage image par image) : les alternatives viennent de la médiathèque.
     */
    public static function galleryInformative(string $name = self::GALLERY_INFORMATIVE): array
    {
        if (!self::accessibilityEnabled()) {
            return [];
        }

        return [TrueFalseField::make(__('Les images portent une information', 'horizon-tools'), $name)
            ->helperText(__("À cocher si les images apportent une information (produits, réalisations, schémas). Chaque image doit alors avoir un texte alternatif dans la médiathèque. Sinon, elles sont ignorées par les lecteurs d'écran.", 'horizon-tools'))
            ->default(false)];
    }

    public static function accessibilityEnabled(): bool
    {
        return (bool) Config::get(self::CONFIG_ACCESSIBILITY, false);
    }

    public static function isDecorative(?array $fields, string $imageName = self::IMAGE): bool
    {
        return empty($fields[self::informativeName($imageName)]);
    }

    /**
     * Texte alternatif saisi pour ce contexte, ou null : image décorative, ou informative avec l'alternative de la médiathèque 
     */
    public static function alt(?array $fields, string $imageName = self::IMAGE): ?string
    {
        if (self::isDecorative($fields, $imageName)) {
            return null;
        }

        $alt = trim((string) ($fields[self::altName($imageName)] ?? ''));

        return '' !== $alt ? $alt : null;
    }

    public static function informativeName(string $imageName = self::IMAGE): string
    {
        return $imageName . self::INFORMATIVE_SUFFIX;
    }

    public static function altName(string $imageName = self::IMAGE): string
    {
        return $imageName . self::ALT_SUFFIX;
    }
}
