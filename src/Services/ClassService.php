<?php

declare(strict_types=1);

namespace Adeliom\HorizonTools\Services;

use Adeliom\HorizonTools\Admin\AbstractAdmin;
use Adeliom\HorizonTools\Blocks\AbstractBlock;
use Adeliom\HorizonTools\PostTypes\AbstractPostType;
use Adeliom\HorizonTools\Taxonomies\AbstractTaxonomy;
use Adeliom\HorizonTools\Templates\AbstractTemplate;
use Composer\InstalledVersions;
use ReflectionClass;
use ReflectionException;

class ClassService
{
    public static function getClassNameFromFullName(string $fullName): ?string
    {
        preg_match("/\\\\([^\\\\]+)$/", $fullName, $m);

        if (isset($m[1])) {
            return $m[1];
        }

        return null;
    }

    public static function getFolderNameFromFullName(string $fullName, array $replacements = []): ?string
    {
        $string = explode('\\', $fullName);

        if (!empty($replacements)) {
            // Remove the last element (the class name)
            array_pop($string);
            $path = strtolower(implode('/', $string));

            return str_replace(array_keys($replacements), array_values($replacements), $path);
        }

        return strtolower($string[count($string) - 2]);
    }

    public static function slugifyClassName(string $className): string
    {
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '-$0', $className));
    }

    public static function getAllCustomAdminClasses(): array
    {
        return array_filter(get_declared_classes(), function ($class) {
            return is_subclass_of($class, AbstractAdmin::class);
        });
    }

    public static function getAllCustomOptionPages(bool $onlyRoot = false): array
    {
        return array_values(
            array_filter(
                array_map(function (string $class) use ($onlyRoot) {
                    if ($class::$isOptionPage) {
                        if (!$onlyRoot) {
                            return $class;
                        } else {
                            $classInstance = new $class();

                            if (method_exists($classInstance, 'getOptionPageParent')) {
                                if ($classInstance->getOptionPageParent() === null) {
                                    return $class;
                                }
                            }
                        }
                    }

                    return null;
                }, self::getAllCustomAdminClasses())
            )
        );
    }

    public static function getAllCustomPostTypeClasses(): array
    {
        return array_values(
            array_filter(get_declared_classes(), function ($class) {
                return is_subclass_of($class, AbstractPostType::class);
            })
        );
    }

    public static function getAllCustomBlockClasses(): array
    {
        return array_filter(get_declared_classes(), function ($class) {
            return is_subclass_of($class, AbstractBlock::class);
        });
    }

    public static function getAllCustomBlockClassesWithSlugAsKey(): array
    {
        $classes = [];

        foreach (self::getAllCustomBlockClasses() as $customBlockClass) {
            if (isset($customBlockClass::$slug)) {
                $classes[$customBlockClass::$slug] = $customBlockClass;
            }
        }

        return $classes;
    }

    private static function getAllCustomBlockClassesByAllowedOrNotInSummary(bool $allowed): array
    {
        $classes = [];

        foreach (self::getAllCustomBlockClasses() as $customBlockClass) {
            if (isset($customBlockClass::$slug, $customBlockClass::$inSummary)) {
                if ($customBlockClass::$inSummary === $allowed) {
                    $classes[$customBlockClass::$slug] = $customBlockClass;
                }
            }
        }

        return $classes;
    }

    public static function getAllCustomBlockClassesAllowedInSummary(): array
    {
        return self::getAllCustomBlockClassesByAllowedOrNotInSummary(allowed: true);
    }

    public static function getAllCustomBlockClassesNotAllowedInSummary(): array
    {
        return self::getAllCustomBlockClassesByAllowedOrNotInSummary(allowed: false);
    }

    public static function getAllCustomTaxonomyClasses(): array
    {
        return array_values(
            array_filter(get_declared_classes(), function ($class) {
                return is_subclass_of($class, AbstractTaxonomy::class);
            })
        );
    }

    public static function getAllCustomTemplateClasses(): array
    {
        return array_filter(get_declared_classes(), function ($class) {
            return is_subclass_of($class, AbstractTemplate::class);
        });
    }

    public static function isHorizonBlocksInstalled(): bool
    {
        return InstalledVersions::isInstalled('agence-adeliom/horizon-blocks');
    }

    public static function isLivewireInstalled(): bool
    {
        return InstalledVersions::isInstalled('livewire/livewire');
    }

    public static function isAcfInstalledAndEnabled(): bool
    {
        return class_exists('ACF');
    }

    public static function getAllImportableBlockClasses(): array
    {
        $classes = [];

        if (ClassService::isHorizonBlocksInstalled()) {
            if ($pathToDependency = InstalledVersions::getInstallPath('agence-adeliom/horizon-blocks')) {
                if ($blocks = FileService::getClassesPathsFromPath($pathToDependency . '/src/Blocks')) {
                    foreach ($blocks as $block) {
                        // Get classname from $block path
                        if ($blockClassName = ClassService::getClassNameFromFilePath($block)) {
                            $classes[$block] = $blockClassName;
                        }
                    }
                }
            }
        }

        return $classes;
    }

    public static function getAllClassesFromPath(string $path): array
    {
        $classes = [];

        if (is_dir($path)) {
            $files = FileService::getClassesPathsFromPath($path);

            foreach ($files as $file) {
                if ($className = self::getClassNameFromFilePath($file)) {
                    $classes[$file] = $className;
                }
            }
        }

        return $classes;
    }

    public static function getClassNameFromFilePath(string $filePath): ?string
    {
        $content = file_get_contents($filePath);
        if ($content === false) {
            return null;
        }

        $namespace = null;
        $class = null;

        if (preg_match('/namespace\s+([^;]+);/', $content, $matches)) {
            $namespace = $matches[1];
        }

        if (preg_match('/class\s+([^\s]+)/', $content, $matches)) {
            $class = $matches[1];
        }

        if ($namespace && $class) {
            return $namespace . '\\' . $class;
        }

        return null;
    }

    public static function getPostTypeClassBySlug(string $slug): ?string
    {
        $class = null;

        foreach (self::getAllCustomPostTypeClasses() as $postTypeClass) {
            if (class_exists($postTypeClass)) {
                if ($postTypeClass::$slug === $slug) {
                    $class = $postTypeClass;
                    break;
                }
            }
        }

        return $class;
    }

    public static function getFilePathFromClassName(string $className): ?string
    {
        try {
            $reflector = new ReflectionClass($className);
            return $reflector->getFileName();
        } catch (ReflectionException $e) {
            return null;
        }
    }
}
