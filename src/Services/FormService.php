<?php

declare(strict_types=1);

namespace Adeliom\HorizonTools\Services;

use GFFormsModel;

class FormService
{
    public static function isGravityFormsActive(): bool
    {
        if (function_exists('is_plugin_active')) {
            return is_plugin_active('gravityforms/gravityforms.php');
        }

        return false;
    }

    public static function getAllForms(): array
    {
        $forms = [];

        if (class_exists('GFFormsModel')) {
            $forms = GFFormsModel::get_forms();
        }

        return $forms;
    }

    public static function getAllFormChoices(): array
    {
        $choices = [];

        foreach (self::getAllForms() as $form) {
            $choices[$form->id] = $form->title;
        }

        return $choices;
    }
}
