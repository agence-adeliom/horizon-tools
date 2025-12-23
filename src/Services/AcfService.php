<?php

declare(strict_types=1);

namespace Adeliom\HorizonTools\Services;

use Adeliom\HorizonTools\Services\AcfService as HorizonToolsAcfService;
use App\Taxonomies\RealEstate\Data\RealEstateAgentTaxonomy;
use Extended\ACF\Fields\Group;
use Extended\ACF\Fields\Repeater;
use Extended\ACF\Key;

class AcfService
{
    public static function getFieldKey(string $fieldName, string $entityTypeSlug, bool $isTaxonomy = false): string
    {
        if ($isTaxonomy) {
            $notHashedKey = sprintf('group_taxonomy_%s_%s', strtolower(str_replace(['-'], ['_'], $entityTypeSlug)), $fieldName);
        } else {
            $notHashedKey = sprintf('group_%s_%s', strtolower(str_replace(['-'], ['_'], $entityTypeSlug)), $fieldName);
        }

        return sprintf('field_%s', Key::hash($notHashedKey));
    }

    public static function metaSecureUpdate(
        int $entityId,
        string $entityTypeSlug,
        string $metaKey,
        mixed $metaValue,
        bool $isTerm = false
    ): void {
        if ($isTerm) {
            update_term_meta(term_id: $entityId, meta_key: $metaKey, meta_value: $metaValue);
            update_term_meta(
                term_id: $entityId,
                meta_key: sprintf('_%s', $metaKey),
                meta_value: self::getFieldKey(fieldName: $metaKey, entityTypeSlug: $entityTypeSlug, isTaxonomy: true)
            );
        } else {
            update_post_meta(post_id: $entityId, meta_key: $metaKey, meta_value: $metaValue);
            update_post_meta(
                post_id: $entityId,
                meta_key: sprintf('_%s', $metaKey),
                meta_value: self::getFieldKey(fieldName: $metaKey, entityTypeSlug: $entityTypeSlug)
            );
        }
    }

    public static function emptyRepeaterField(string $key, int $postId): void
    {
        global $wpdb;

        delete_post_meta(post_id: $postId, meta_key: $key);
        delete_post_meta(post_id: $postId, meta_key: sprintf('_%s', $key));

        $sqlQuery = <<<SQL
DELETE FROM {$wpdb->postmeta}
WHERE post_id = $postId AND (meta_key LIKE '{$key}_%' OR meta_key LIKE '_{$key}_%');
SQL;

        $wpdb->query($sqlQuery);
    }

    public static function updateRepeaterField(string $key, array $rows, int $postId, string $postTypeSlug): void
    {
        // Update the repeater field value (counter of elements)
        update_post_meta(post_id: $postId, meta_key: $key, meta_value: count($rows));

        // Update the repeater field key
        update_post_meta(
            post_id: $postId,
            meta_key: sprintf('_%s', $key),
            meta_value: self::getFieldKey(fieldName: $key, entityTypeSlug: $postTypeSlug)
        );

        foreach ($rows as $index => $row) {
            foreach ($row as $fieldKey => $fieldValue) {
                update_post_meta(post_id: $postId, meta_key: sprintf('%s_%d_%s', $key, $index, $fieldKey), meta_value: $fieldValue);
                update_post_meta(
                    post_id: $postId,
                    meta_key: sprintf('_%s_%d_%s', $key, $index, $fieldKey),
                    meta_value: self::getFieldKey(fieldName: sprintf('%s_%s', $key, $fieldKey), entityTypeSlug: $postTypeSlug)
                );
            }
        }
    }

    public static function getChoices(iterable $fields, string $fullMetaKey, ?string &$buildKey = '', bool $first = true)
    {
        foreach ($fields as $field) {
            if ($first) {
                $buildKey = '';
            }
            switch (true) {
                case $field instanceof Group:
                case $field instanceof Repeater:
                    foreach ((array) $field as $key => $value) {
                        if (str_contains($key, 'settings')) {
                            if (isset($value['sub_fields'])) {
                                if (!empty($buildKey)) {
                                    $buildKey .= '_';
                                }

                                $buildKey .= $value['name'];

                                if ($results = self::getChoices($value['sub_fields'], $fullMetaKey, $buildKey, false)) {
                                    return $results;
                                }
                            }
                        }
                    }
                    break;
                default:
                    break;
            }

            foreach ((array) $field as $key => $value) {
                if (str_contains($key, 'settings')) {
                    if (isset($value['name'])) {
                        $shouldBe = empty($buildKey) ? $value['name'] : $buildKey . '_' . $value['name'];

                        if ($shouldBe === $fullMetaKey) {
                            if (isset($value['choices'])) {
                                return $value['choices'];
                            }
                        }
                    }
                }
            }
        }

        return false;
    }

    public static function getAllOptionPages(): array
    {
        $pages = [];

        return $pages;
    }
}
