<?php

declare(strict_types=1);

namespace Adeliom\HorizonTools\Providers;

use Adeliom\HorizonTools\PostTypes\GravityFormConfirmationPageType;
use Adeliom\HorizonTools\Services\FormService;
use Illuminate\Support\Facades\Config;
use Roots\Acorn\Sage\SageServiceProvider;

class FormsServiceProvider extends SageServiceProvider
{
    private const VALUE_TYPE_CUSTOM_PAGE = 'customPage';
    private const INSERT_CUSTOM_PAGE_FIELDS_AFTER_PAGE_FIELD = true;
    private const DISABLE_CLASSIC_PAGE_FIELDS = true;

    public function boot(): void
    {
        $this->initGravityFormsConfirmationPagePostType();
        $this->initGravityFormsConfirmationFields();
    }

    private function isCustomConfirmationPagePostTypeEnabled(): bool
    {
        return Config::get('gravityforms.confirmations.enablePostType') ?? false;
    }

    private function getConfirmationPostTypeClass(): string
    {
        return Config::get('gravityforms.confirmations.postType') ?? GravityFormConfirmationPageType::class;
    }

    private function initGravityFormsConfirmationPagePostType(): void
    {
        if (FormService::isGravityFormsActive()) {
            $enablePostType = $this->isCustomConfirmationPagePostTypeEnabled();
            $postType = $this->getConfirmationPostTypeClass();

            if ($enablePostType && class_exists($postType)) {
                PostTypeServiceProvider::initPostTypeByClassName($postType);
            }
        }
    }

    private function initGravityFormsConfirmationFields(): void
    {
        if (FormService::isGravityFormsActive()) {
            $enablePostType = $this->isCustomConfirmationPagePostTypeEnabled();

            if ($enablePostType) {
                add_filter(
                    hook_name: 'gform_post_select_post_types',
                    callback: function ($postTypes) {
                        $postTypes[] = GravityFormConfirmationPageType::$slug;

                        return $postTypes;
                    }
                );

                add_filter(
                    hook_name: 'gform_confirmation_settings_fields',
                    callback: function ($fields, $confirmation, $form) {
                        if (empty($fields[0]['fields'])) {
                            return $fields;
                        }

                        $customFieldCreated = false;
                        $pageFieldRemoved = false;
                        $pageChoiceRemoved = false;

                        foreach ($fields[0]['fields'] as $fieldKey => &$field) {
                            if (!empty($field['name']) && $field['name'] === 'type') {
                                if (!empty($field['choices'])) {
                                    $choiceField = [
                                        'label' => __('Page de confirmation'),
                                        'value' => self::VALUE_TYPE_CUSTOM_PAGE,
                                    ];

                                    if (
                                        self::INSERT_CUSTOM_PAGE_FIELDS_AFTER_PAGE_FIELD &&
                                        ($key = array_find_key($field['choices'], fn($choice) => $choice['value'] === 'page'))
                                    ) {
                                        array_splice($field['choices'], $key, 0, [$choiceField]);
                                    }

                                    if (self::DISABLE_CLASSIC_PAGE_FIELDS) {
                                        foreach ($field['choices'] as $choiceKey => $choiceData) {
                                            if (!empty($choiceData['value']) && $choiceData['value'] === 'page') {
                                                unset($field['choices'][$choiceKey]);
                                                $pageChoiceRemoved = true;
                                                break;
                                            }
                                        }
                                    }

                                    if (!self::INSERT_CUSTOM_PAGE_FIELDS_AFTER_PAGE_FIELD) {
                                        $field['choices'][] = $choiceField;
                                    }
                                }

                                $customFieldCreated = true;
                            }

                            if (!empty($field['name']) && $field['name'] === 'page') {
                                $field['post_type'] = 'page';

                                if (self::DISABLE_CLASSIC_PAGE_FIELDS) {
                                    unset($fields[0]['fields'][$fieldKey]);
                                    $pageFieldRemoved = true;
                                }
                            }
                        }

                        $customField = [
                            'name' => self::VALUE_TYPE_CUSTOM_PAGE,
                            'label' => esc_html__('Page de confirmation'),
                            'type' => 'post_select',
                            'required' => true,
                            'post_type' => GravityFormConfirmationPageType::$slug,
                            'dependency' => [
                                'live' => true,
                                'operator' => 'ALL',
                                'fields' => [
                                    [
                                        'field' => 'type',
                                        'values' => [self::VALUE_TYPE_CUSTOM_PAGE],
                                    ],
                                ],
                            ],
                        ];

                        if (
                            $key = array_find_key($fields[0]['fields'], fn($field) => !empty($field['type']) && $field['type'] === 'save')
                        ) {
                            array_splice($fields[0]['fields'], $key, 0, [$customField]);
                        } else {
                            $fields[0]['fields'][] = $customField;
                        }

                        return $fields;
                    },
                    priority: 10,
                    accepted_args: 3
                );
            }
        }
    }
}
