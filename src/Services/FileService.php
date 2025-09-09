<?php

declare(strict_types=1);

namespace Adeliom\HorizonTools\Services;

use Adeliom\HorizonTools\PostTypes\GravityFormConfirmationPageType;
use Composer\InstalledVersions;
use FilesystemIterator;

class FileService
{
    public static function getClassesPathsFromPath(string $path): array
    {
        $classes = [];

        if (file_exists($path)) {
            $Directory = new \RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS);
            $Iterator = new \RecursiveIteratorIterator($Directory);
            $Regex = new \RegexIterator($Iterator, '/^.+\.php$/i', \RecursiveRegexIterator::GET_MATCH);

            foreach ($Regex as $file) {
                foreach ($file as $element) {
                    if (str_ends_with(basename($element), '.php')) {
                        $classes[] = $element;
                    }
                }
            }

            // Sort classes by name
            usort($classes, function ($a, $b) {
                $nameA = basename($a);
                $nameB = basename($b);

                if ($nameA < $nameB) {
                    return -1;
                } elseif ($nameA > $nameB) {
                    return 1;
                }

                return 0;
            });
        }

        return $classes;
    }

    public static function getCustomTaxonomyFiles(): array
    {
        if (function_exists('get_template_directory')) {
            return self::getClassesPathsFromPath(get_template_directory() . '/app/Taxonomies');
        }

        return [];
    }

    public static function getCustomPostTypeFiles(): array
    {
        if (function_exists('get_template_directory')) {
            return self::getClassesPathsFromPath(get_template_directory() . '/app/PostTypes');
        }

        return [];
    }

    public static function getHorizonPostTypeFiles(bool $withExcluded = false): array
    {
        if ($folder = InstalledVersions::getInstallPath('agence-adeliom/horizon-tools')) {
            $postTypesPath = sprintf('%s/src/PostTypes', $folder);

            if (file_exists($postTypesPath)) {
                $classPaths = self::getClassesPathsFromPath($postTypesPath);

                if (!$withExcluded) {
                    foreach ($classPaths as $classPathKey => $classPathValue) {
                        if (in_array(ClassService::getClassNameFromFilePath($classPathValue), [GravityFormConfirmationPageType::class])) {
                            unset($classPaths[$classPathKey]);
                        }
                    }
                }

                return $classPaths;
            }
        }

        return [];
    }

    public static function getCustomTemplateFiles(): array
    {
        if (function_exists('get_template_directory')) {
            return self::getClassesPathsFromPath(get_template_directory() . '/app/Templates');
        }

        return [];
    }

    public static function getCustomHookFiles(): array
    {
        if (function_exists('get_template_directory')) {
            return self::getClassesPathsFromPath(get_template_directory() . '/app/Hooks');
        }

        return [];
    }

    public static function getCustomBlockFiles(): array
    {
        if (function_exists('get_template_directory')) {
            return self::getClassesPathsFromPath(get_template_directory() . '/app/Blocks');
        }

        return [];
    }

    public static function getCustomAdminFiles(): array
    {
        if (function_exists('get_template_directory')) {
            return self::getClassesPathsFromPath(get_template_directory() . '/app/Admin');
        }

        return [];
    }

    public static function filePutContentsAndCreateMissingDirectories(string $filePath, string $data): bool
    {
        $directory = dirname($filePath);

        if (!is_dir($directory)) {
            if (!mkdir($directory, 0755, true) && !is_dir($directory)) {
                return false;
            }
        }

        return file_put_contents($filePath, $data) !== false;
    }

    public static function getPathToWpConfigFolder(): ?string
    {
        return ABSPATH . '../';
    }
}
