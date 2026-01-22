<?php

declare(strict_types=1);

namespace Adeliom\HorizonTools\Services;

class MediaLibraryService
{
    public static function importMediaFromUrl(string $url, string $alt = '', array $meta = [], int $postId = 0): null|int|\WP_Post|\WP_Error
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

        // Vérifier si le fichier a une extension
        $pathInfo = pathinfo($fileName);
        if (empty($pathInfo['extension'])) {
            // Essayer de deviner l'extension à partir du Content-Type
            $response = wp_remote_head($url);

            if (!is_wp_error($response)) {
                $contentType = wp_remote_retrieve_header($response, 'content-type');

                if ($contentType) {
                    $mimeTypes = [
                        'image/jpeg' => 'jpg',
                        'image/png' => 'png',
                        'image/gif' => 'gif',
                        'image/webp' => 'webp',
                        'image/svg+xml' => 'svg',
                        'application/pdf' => 'pdf',
                        'video/mp4' => 'mp4',
                        'video/webm' => 'webm',
                    ];

                    foreach ($mimeTypes as $mime => $extension) {
                        if (str_contains($contentType, $mime)) {
                            $fileName .= '.' . $extension;
                            break;
                        }
                    }
                }
            }
        }

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
