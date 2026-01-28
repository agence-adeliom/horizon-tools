<?php

declare(strict_types=1);

namespace Adeliom\HorizonTools\Fields\Text;

use Extended\ACF\Fields\Group;
use Extended\ACF\Fields\Select;
use Extended\ACF\Fields\Text;

class HeadingField
{
    final public const LABEL = 'Titre';
    final public const TAGS_LABEL = 'Tag HTML';
    final public const NAME = 'title';
    final public const TAGS_NAME = 'tag';
    final public const CONTENT_NAME = 'content';
    final public const DEFAULT_TAGS = [
        'div' => 'div',
        'h1' => 'h1',
        'h2' => 'h2',
        'h3' => 'h3',
        'h4' => 'h4',
        'h5' => 'h5',
    ];

    public static function make(
        string $label = self::LABEL,
        ?string $name = self::NAME,
        ?array $tags = null,
        ?string $defaultTag = null,
        ?string $tagInstructions = null,
        string $textFieldClass = Text::class
    ): Group {
        return Group::make(__($label), $name)->fields([
            self::getTagsField(tags: $tags, default: $defaultTag, instructions: $tagInstructions),
            self::getTitleField(label: $label, textFieldClass: $textFieldClass),
        ]);
    }

    private static function getTitleField(
        string $label = self::LABEL,
        ?string $name = self::CONTENT_NAME,
        string $textFieldClass = Text::class
    ): Text {
        if ($textFieldClass === Text::class || !class_exists($textFieldClass) || !method_exists($textFieldClass, 'make')) {
            return Text::make($label, $name);
        }

        return $textFieldClass::make($label, $name);
    }

    private static function getTagsField(?array $tags = null, ?string $default = null, ?string $instructions = null): Select
    {
        if (null === $tags) {
            $tags = self::DEFAULT_TAGS;
        }

        if (null === $default) {
            $default = 'div';
        }

        $select = Select::make(__(self::TAGS_LABEL), self::TAGS_NAME)
            ->choices($tags)
            ->default($default)
            ->stylized();

        if (null !== $instructions) {
            $select->helperText($instructions);
        }

        return $select;
    }
}
