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
            $this->settings['instructions'] = sprintf(__('Ratio recommandé : %dx%dpx', 'horizon-tools'), $width, $height);
        } elseif (null !== $width) {
            $this->settings['instructions'] = sprintf(__('Largeur recommandée : %dpx', 'horizon-tools'), $width);
        } elseif (null !== $height) {
            $this->settings['instructions'] = sprintf(__('Hauteur recommandée : %dpx', 'horizon-tools'), $height);
        }
        return $this;
    }
}
