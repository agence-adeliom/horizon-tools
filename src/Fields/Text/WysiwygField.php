<?php

declare(strict_types=1);

namespace Adeliom\HorizonTools\Fields\Text;

use Extended\ACF\Fields\WysiwygEditor;

class WysiwygField
{
    final public const WYSIWYG = "wysiwyg";
    final public const TOOLBAR_DEFAULT = 'default';
    final public const TOOLBAR_SIMPLE = 'simple';

    public static function make(string $label = "Description", string|null $name = self::WYSIWYG): static
    {
        return WysiwygEditor::make($label, $name);
    }

    public function default(): static
    {
        $this->settings['toolbar'] = self::TOOLBAR_DEFAULT;
        $this->settings['media_upload'] = false;
        return $this;
    }

    public function simple(): static
    {
        $this->settings['toolbar'] = self::TOOLBAR_SIMPLE;
        $this->settings['media_upload'] = false;
        return $this;
    }
}