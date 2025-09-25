<?php

declare(strict_types=1);

namespace Adeliom\HorizonTools\Providers;

use Adeliom\HorizonTools\PostTypes\GravityFormConfirmationPageType;
use Adeliom\HorizonTools\Services\FormService;
use Composer\InstalledVersions;
use Illuminate\Support\Facades\Config;
use Roots\Acorn\Sage\SageServiceProvider;

class FormsServiceProvider extends SageServiceProvider
{
    public const VALUE_TYPE_CUSTOM_PAGE = 'page';
    private const VALUE_TYPE_CUSTOM_PAGE_GET_PARAMS = 'customPageWithGetParams';
    private const INSERT_CUSTOM_PAGE_FIELDS_AFTER_PAGE_FIELD = true;
    private const DISABLE_CLASSIC_PAGE_FIELDS = true;

    public function boot(): void
    {
        $this->initGravityFormsConfirmationPagePostType();
        $this->initGravityFormsConfirmationFields();
        $this->initGravityFormsConfirmationRoutes();
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

                add_action(
                    hook_name: 'gform_confirmation',
                    callback: function ($confirmation, $form, $entry, $isAjax) {
                        if ($this->isCustomConfirmationPagePostTypeEnabled()) {
                            if (empty($form['confirmation']['type']) || $form['confirmation']['type'] !== self::VALUE_TYPE_CUSTOM_PAGE) {
                                return $confirmation;
                            }

                            if (is_array($confirmation)) {
                                if (!empty($confirmation['redirect']) && !empty($form['confirmation']['pageId'])) {
                                    $pageId = $form['confirmation']['pageId'];
                                    $confirmationPostTypeClass = $this->getConfirmationPostTypeClass();
                                    $confirmationPostTypeSlug = property_exists($confirmationPostTypeClass, 'slug')
                                        ? $confirmationPostTypeClass::$slug
                                        : null;

                                    if (null === $confirmationPostTypeSlug) {
                                        return $confirmation;
                                    }

                                    if (!is_numeric($pageId)) {
                                        return $confirmation;
                                    }

                                    $pageId = intval($pageId);
                                    $post = get_post($pageId);

                                    if ($post->post_type !== $confirmationPostTypeSlug) {
                                        return $confirmation;
                                    }

                                    $parentPage = get_field(
                                        sprintf(
                                            '%s_%s',
                                            GravityFormConfirmationPageType::FIELD_CONFIGURATION,
                                            GravityFormConfirmationPageType::FIELD_PARENT
                                        ),
                                        $post
                                    );

                                    $parentUrl = get_permalink($parentPage);

                                    // Get GET paramaters from confirmation redirect URL
                                    $parsedUrl = parse_url($confirmation['redirect']);

                                    $query = [];

                                    if (!empty($parsedUrl['query'])) {
                                        parse_str($parsedUrl['query'], $query);
                                        unset($query[$confirmationPostTypeSlug]);
                                    }

                                    if ($parentPage) {
                                        $redirectUrl = sprintf('%s%s/', $parentUrl, $post->post_name);
                                    } else {
                                        $redirectUrl = sprintf('%s/%s/', home_url(), $post->post_name);
                                    }

                                    if (!empty($query)) {
                                        $redirectUrl = sprintf('%s?%s', $redirectUrl, http_build_query($query));
                                    }

                                    $confirmation['redirect'] = $redirectUrl;
                                }
                            }
                        }

                        return $confirmation;
                    },
                    accepted_args: 4
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
                            if (!empty($field['name'])) {
                                switch ($field['name']) {
                                    case 'type':
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
                                        break;
                                    case 'queryString':
                                        $field['dependency']['fields'][0]['values'][] = self::VALUE_TYPE_CUSTOM_PAGE;
                                        break;
                                    default:
                                        break;
                                }
                            }

                            if (!empty($field['name']) && $field['name'] === 'page') {
                                $field['post_type'] = 'page';

                                if (self::DISABLE_CLASSIC_PAGE_FIELDS) {
                                    unset($fields[0]['fields'][$fieldKey]);
                                    $pageFieldRemoved = true;
                                }
                            }
                        }

                        $customFields = [];
                        $customFieldsDependency = [
                            'live' => true,
                            'operator' => 'ALL',
                            'fields' => [
                                [
                                    'field' => 'type',
                                    'values' => [self::VALUE_TYPE_CUSTOM_PAGE],
                                ],
                            ],
                        ];

                        $customFields[] = [
                            'name' => self::VALUE_TYPE_CUSTOM_PAGE,
                            'label' => esc_html__('Page de confirmation'),
                            'type' => 'post_select',
                            'required' => true,
                            'post_type' => GravityFormConfirmationPageType::$slug,
                            'dependency' => $customFieldsDependency,
                        ];

                        if (
                            $key = array_find_key(
                                $fields[0]['fields'],
                                fn($field) => !empty($field['name']) && $field['name'] === 'queryString'
                            )
                        ) {
                            array_splice($fields[0]['fields'], $key - 1, 0, $customFields);
                        } else {
                            $fields[0]['fields'] = array_merge($fields[0]['fields'], $customFields);
                        }

                        return $fields;
                    },
                    priority: 10,
                    accepted_args: 3
                );
            }
        }
    }

    private function initGravityFormsConfirmationRoutes(): void
    {
        if (FormService::isGravityFormsActive() && $this->isCustomConfirmationPagePostTypeEnabled()) {
            $pathToRoutes = sprintf(
                '%s/routes/gravity-form-confirmation-routes.php',
                InstalledVersions::getInstallPath('agence-adeliom/horizon-tools')
            );

            if (file_exists($pathToRoutes)) {
                $this->loadRoutesFrom($pathToRoutes);
            }
        }
    }
}
