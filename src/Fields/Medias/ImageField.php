<?php

declare(strict_types=1);

namespace Adeliom\HorizonTools\Fields\Medias;

use Extended\ACF\Fields\Image;

class ImageField extends Image
{
    final public const IMAGE = 'image';

    public static function make(string $label = 'Image', ?string $name = self::IMAGE): static
    {
        return parent::make($label, $name)->library('all')->format('array');
    }

    public function ratio(?int $width = null, ?int $height = null): static
    {
        if (null !== $width && null !== $height) {
            $this->settings['instructions'] = 'Ratio recommandé : ' . $width . 'x' . $height . 'px';
        } elseif (null !== $width) {
            $this->settings['instructions'] = 'Largeur recommandée : ' . $width . 'px';
        } elseif (null !== $height) {
            $this->settings['instructions'] = 'Hauteur recommandée : ' . $height . 'px';
        }
        return $this;
    }
}
