<?php

declare(strict_types=1);

namespace Adeliom\HorizonTools\Fields\Text;

use Extended\ACF\Fields\WYSIWYGEditor;

class WysiwygField
{
    final public const WYSIWYG = 'wysiwyg';

    final public const TOOLBAR_DEFAULT = 'default';
    final public const TOOLBAR_SIMPLE = 'simple';
    final public const TOOLBAR_MINIMAL = 'minimal';

    public static function make(string $label = 'Description', string|null $name = self::WYSIWYG): WYSIWYGEditor
    {
        return WYSIWYGEditor::make($label, $name);
    }

    public static function default(string $label = 'Description', string|null $name = self::WYSIWYG): WYSIWYGEditor
    {
        return self::make($label, $name)
            ->toolbar(self::TOOLBAR_DEFAULT)
            ->disableMediaUpload(false);
    }

    public static function simple(string $label = 'Description', string|null $name = self::WYSIWYG): WYSIWYGEditor
    {
        return self::make($label, $name)
            ->toolbar(self::TOOLBAR_SIMPLE)
            ->disableMediaUpload(false);
    }

    public static function minimal(string $label = 'Description', string|null $name = self::WYSIWYG): WYSIWYGEditor
    {
        return self::make($label, $name)
            ->toolbar(self::TOOLBAR_MINIMAL)
            ->disableMediaUpload(false);
    }
}
