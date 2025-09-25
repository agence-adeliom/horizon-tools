<?php

use Illuminate\Support\Facades\Route;
use Adeliom\HorizonTools\PostTypes\GravityFormConfirmationPageType;
use Adeliom\HorizonTools\Providers\FormsServiceProvider;
use Adeliom\HorizonTools\Services\PostService;

foreach (GFAPI::get_forms() as $form) {
    if (!empty($form['confirmations'])) {
        foreach ($form['confirmations'] as $confirmation) {
            if (!empty($confirmation['type']) && $confirmation['type'] === FormsServiceProvider::VALUE_TYPE_CUSTOM_PAGE) {
                if (!empty($confirmation['pageId']) && is_numeric($confirmation['pageId'])) {
                    $pageId = intval($confirmation['pageId']);

                    if ($post = get_post($pageId)) {
                        $metaKey = sprintf(
                            '%s_%s',
                            GravityFormConfirmationPageType::FIELD_CONFIGURATION,
                            GravityFormConfirmationPageType::FIELD_PARENT
                        );

                        $confirmationPageParentId = get_post_meta($pageId, $metaKey, true);

                        if (is_numeric($confirmationPageParentId)) {
                            $confirmationPageParentId = intval($confirmationPageParentId);
                        } else {
                            $confirmationPageParentId = null;
                        }

                        if ($confirmationPageParentId) {
                            // Case with a parent page
                            $parentPage = get_post($confirmationPageParentId);

                            if ($parentUrl = get_permalink($parentPage)) {
                                $parsedParentUrl = parse_url($parentUrl);
                                $parentPath = $parsedParentUrl['path'];
                                $fullPath = sprintf('%s%s/', $parentPath, $post->post_name);

                                Route::get($fullPath, function () use ($post) {
                                    return PostService::renderPost(post: $post);
                                });
                            }
                        } else {
                            // Case without a parent page
                            $fullPath = $post->post_name;

                            Route::get($fullPath, function () use ($post) {
                                return PostService::renderPost(post: $post);
                            });
                        }
                    }
                }
            }
        }
    }
}
