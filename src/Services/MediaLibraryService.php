<?php

declare(strict_types=1);

namespace Adeliom\HorizonTools\Services;

class MediaLibraryService
{
    public static function importMediaFromUrl(string $url, string $alt = '', array $meta = [], int $postId = 0): null|int|\WP_Post
    {
        // Nécessite les fichiers de l'API de média de WordPress
        if (!function_exists('media_handle_upload')) {
            require_once ABSPATH . 'wp-admin/includes/image.php';
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
        }

        $fileContent = wp_remote_retrieve_body(wp_remote_get($url));

        if (is_wp_error($fileContent)) {
            return null;
        }

        $fileName = basename($url);

        $upload = wp_upload_bits($fileName, null, $fileContent);

        if ($upload['error']) {
            return new \WP_Error('upload_error', $upload['error']);
        }

        $filePath = $upload['file'];
        $fileName = basename($filePath);
        $fileType = wp_check_filetype($fileName);

        $attachmentData = [
            'guid' => $upload['url'],
            'post_mime_type' => $fileType['type'],
            'post_title' => sanitize_file_name($fileName),
            'post_content' => '',
            'post_status' => 'inherit',
        ];

        $attachmentId = wp_insert_attachment($attachmentData, $filePath, $postId);

        if (is_wp_error($attachmentId)) {
            return null;
        }

        try {
            $attachmentMeta = @wp_generate_attachment_metadata($attachmentId, $filePath);
            if (empty($attachmentMeta)) {
                $attachmentMeta = ['file' => $fileName];
            }
        } catch (\Exception $e) {
            $attachmentMeta = ['file' => $fileName];
        }

        foreach ($meta as $metaKey => $metaValue) {
            update_post_meta($attachmentId, $metaKey, $metaValue);
        }

        wp_update_attachment_metadata($attachmentId, $attachmentMeta);

        return $attachmentId;
    }
}
