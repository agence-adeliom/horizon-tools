<?php

namespace Adeliom\HorizonTools\Services\Traits;

use Adeliom\HorizonTools\Services\Compilation\CompilationService;
use Adeliom\HorizonTools\Services\VersionService;

trait CompilatorServiceTrait
{
    private static function getManifestPath(): ?string
    {
        return self::getBuildDirectory() . 'manifest.json';
    }

    private static function getManifest(): array
    {
        $path = self::getManifestPath();

        if (file_exists($path)) {
            return json_decode(file_get_contents($path), true);
        } else {
            return [];
        }
    }

    private static function getManifestAssociation(string $handle, bool $returnViteArray = false): false|string|array
    {
        $manifest = self::getManifest();

        if (!empty($manifest)) {
            if (in_array($handle, array_keys($manifest))) {
                $localPath = $manifest[$handle];

                if (CompilationService::shouldUseVite() && $returnViteArray) {
                    return $localPath;
                }

                $localPath = match (true) {
                    CompilationService::shouldUseVite() => $localPath['file'],
                    default => $localPath,
                };

                if (file_exists(sprintf('%s%s', self::getBuildDirectory(), $localPath))) {
                    return sprintf('%s%s', self::getBuildDirectory(full: false), $localPath);
                } else {
                    return false;
                }

                return $manifest[$handle];
            }
        }

        return false;
    }

    private static function getPublicDirectory(bool $full = true): string
    {
        if ($full) {
            return get_template_directory() . '/public/';
        }

        return '/public/';
    }

    private static function getBuildDirectory(bool $full = true): string
    {
        $publicDirectory = self::getPublicDirectory(full: $full);

        return match (true) {
            CompilationService::shouldUseVite() => $publicDirectory . 'build/',
            default => $publicDirectory,
        };
    }

    private static function getTemplateName(): string
    {
        $parts = explode('/', get_template_directory());

        return last($parts);
    }
}
