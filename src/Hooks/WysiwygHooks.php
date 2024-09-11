<?php

declare(strict_types=1);

namespace Adeliom\HorizonTools\Hooks;

use Adeliom\HorizonTools\Fields\Text\WysiwygField;

class WysiwygHooks extends AbstractHook
{

    public function init(): void
    {
        add_filter('tiny_mce_before_init', [$this, 'removeHeadings']);
        add_filter('mce_buttons', [$this, 'removeButtons']);
        add_filter('mce_buttons_2', [$this, 'removeButtonLine2']);
        add_filter('mce_buttons', [$this, 'addSelect']);
        add_filter('acf/fields/wysiwyg/toolbars', [$this, 'wysiwygToolbars']);
    }

    public static function removeHeadings($headings): array
    {
        $headings['block_formats'] = 'Paragraph=p;Heading 2=h2;Heading 3=h3;Heading 4=h4;Heading 5=h5;';
        return $headings;
    }


    public static function wysiwygToolbars(array $toolbars): array
    {
        $toolbars[WysiwygField::TOOLBAR_DEFAULT] = [];
        $toolbars[WysiwygField::TOOLBAR_DEFAULT][1] = [
            'formatselect',
            'styleselect',
            'bold',
            'italic',
            'underline',
            '|',
            'bullist',
            'numlist',
            '|',
            'link',
            '|',
            'removeformat',
        ];

        $toolbars[WysiwygField::TOOLBAR_SIMPLE] = [];
        $toolbars[WysiwygField::TOOLBAR_SIMPLE][1] = [
            'formatselect',
            'styleselect',
            'bold',
            'italic',
            'underline',
            'bullist',
            'numlist',
            'link',
            'removeformat',
        ];

        $toolbars[WysiwygField::TOOLBAR_MINIMAL] = [];
        $toolbars[WysiwygField::TOOLBAR_MINIMAL][1] = [
            'bold',
            'link',
            'removeformat',
        ];

        return $toolbars;
    }

    public static function removeButtons($buttons): array
    {
        $remove_buttons = [
            //'strikethrough',
            'blockquote',
            'hr',
            // horizontal line
            'alignleft',
            'aligncenter',
            'alignright',
            'wp_more',
            // read more link
            'spellchecker',
            // 'dfw',
            // distraction free writing mode
            'wp_adv',
            // kitchen sink toggle (if removed, kitchen sink will always display)
        ];
        foreach ($buttons as $button_key => $button_value) {
            if (in_array($button_value, $remove_buttons, true)) {
                unset($buttons[$button_key]);
            }
        }

        return $buttons;
    }


    public static function removeButtonLine2($buttons): array
    {
        $remove_buttons = [
            'formatselect',
            'underline',
            'strikethrough',
            'alignjustify',
            'forecolor',
            'outdent',
            'indent',
            'hr'
        ];
        foreach ($buttons as $button_key => $button_value) {
            if (in_array($button_value, $remove_buttons)) {
                unset($buttons[$button_key]);
            }
        }

        return $buttons;
    }

    public static function addSelect($buttons) :array {
        array_unshift($buttons, 'styleselect');
        return $buttons;
    }

}
