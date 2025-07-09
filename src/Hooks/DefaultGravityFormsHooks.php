<?php

declare(strict_types=1);

namespace Adeliom\HorizonTools\Hooks;

use Adeliom\HorizonTools\Services\FormService;

class DefaultGravityFormsHooks extends AbstractHook
{
    public const SETTING_DISPLAY_OPTIONNAL_LABEL_NAME = 'displayOptionnalLabel';
    public const SETTING_DISPLAY_OPTIONNAL_LABEL_LABEL = 'Remplacer le label Nécessaire par un label "facultatif"';
    public const SETTING_DISPLAY_OPTIONNAL_LABEL_TOOLTIP = 'Permet d’afficher le label des champs optionnels à la place du label nécessaire dans les formulaires Gravity Forms.';

    public const SETTING_OPTIONNAL_TEXT_NAME = 'optionnalText';
    public const SETTING_OPTIONNAL_TEXT_LABEL = 'Texte pour les champs facultatifs';
    public const SETTING_OPTIONNAL_TEXT_DEFAULT = 'Facultatif';
    public const SETTING_OPTIONNAL_TEXT_TOOLTIP = 'Texte à afficher pour les champs optionnels. Par défaut, il s’agit de %DEFAULT%.';

    public function init(): void
    {
        if (FormService::isGravityFormsActive()) {
            add_filter('gform_form_settings_fields', [$this, 'handleFormSettings'], 10, 2);
            add_filter('gform_field_content', [$this, 'changeFieldLabels'], 10, 5);
        }
    }

    public function handleFormSettings($settings, $form)
    {
        $toggleField = [
            'name' => self::SETTING_DISPLAY_OPTIONNAL_LABEL_NAME,
            'type' => 'toggle',
            'label' => self::SETTING_DISPLAY_OPTIONNAL_LABEL_LABEL,
            'tooltip' => $this->generateTooltip(self::SETTING_DISPLAY_OPTIONNAL_LABEL_TOOLTIP),
        ];

        $textField = [
            'name' => self::SETTING_OPTIONNAL_TEXT_NAME,
            'type' => 'text',
            'label' => self::SETTING_OPTIONNAL_TEXT_LABEL,
            'tooltip' => $this->generateTooltip(
                str_replace('%DEFAULT%', self::SETTING_OPTIONNAL_TEXT_DEFAULT, self::SETTING_OPTIONNAL_TEXT_TOOLTIP)
            ),
            'default_value' => self::SETTING_OPTIONNAL_TEXT_DEFAULT,
            'dependency' => [
                'live' => true,
                'fields' => [
                    [
                        'field' => self::SETTING_DISPLAY_OPTIONNAL_LABEL_NAME,
                    ],
                ],
            ],
        ];

        $settings['form_options']['fields'][] = $toggleField;
        $settings['form_options']['fields'][] = $textField;

        return $settings;
    }

    public function changeFieldLabels(string $content, \GF_Field $field, mixed $value, int $leadId, int $formId)
    {
        if ($form = \GFAPI::get_form($formId)) {
            $optionalText = !empty($form[self::SETTING_OPTIONNAL_TEXT_NAME])
                ? $form[self::SETTING_OPTIONNAL_TEXT_NAME]
                : self::SETTING_OPTIONNAL_TEXT_DEFAULT;

            if (!empty($form[self::SETTING_DISPLAY_OPTIONNAL_LABEL_NAME]) && 1 == $form[self::SETTING_DISPLAY_OPTIONNAL_LABEL_NAME]) {
                if (property_exists($field, 'isRequired')) {
                    $isRequired = $field->isRequired;

                    if ($isRequired) {
                        $content = preg_replace(
                            '/<span class=[\'"]gfield_required[\'"]>.*?<span class=[\'"]gfield_required gfield_required_text[\'"]>.*?<\/span>.*?<\/span>/',
                            '',
                            $content
                        );
                    } else {
                        $content = preg_replace_callback(
                            '/<label[^>]*>(.*?)<\/label>/s',
                            function ($matches) use ($optionalText) {
                                $label_inner = $matches[1];

                                // Ne pas injecter deux fois si rechargé
                                if (strpos($label_inner, sprintf('(%s)', __($optionalText))) !== false) {
                                    return $matches[0];
                                }

                                $new_label = $label_inner . sprintf(' <span class="gfield_optional">(%s)</span>', __($optionalText));
                                return str_replace($label_inner, $new_label, $matches[0]);
                            },
                            $content
                        );
                    }
                }
            }
        }

        return $content;
    }

    private function generateTooltip(string $content): string
    {
        return <<<EOF
<button onclick="return false;" onkeypress="return false;" class="gf_tooltip tooltip tooltip_form_abimation" aria-label="$content">
	<i class="gform-icon gform-icon--question-mark" aria-hidden="true"></i>
</button>
EOF;
    }
}
