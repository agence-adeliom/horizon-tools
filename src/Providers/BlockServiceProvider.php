<?php

declare(strict_types=1);

namespace Adeliom\HorizonTools\Providers;

use Extended\ACF\Location;
use Adeliom\HorizonTools\Enum\BlockCategoriesEnum;
use Adeliom\HorizonTools\Services\ClassService;
use Adeliom\HorizonTools\Services\FileService;
use Roots\Acorn\Exceptions\SkipProviderException;
use Roots\Acorn\Sage\SageServiceProvider;

class BlockServiceProvider extends SageServiceProvider
{
    public const UNREGISTER_DEFAULT_BLOCKS = true;
    private const THEME_BLOCK_CATEGORIES_CLASS = 'App\Enum\BlockCategoriesEnum';

    public function boot(): void
    {
        add_action('acf/init', function () {
            $this->initBlocks();
        });

        if (self::UNREGISTER_DEFAULT_BLOCKS) {
            add_filter('allowed_block_types_all', [$this, 'unregisterBlocks'], 10, 2);
            add_filter('block_categories_all', [$this, 'registerCustomBlockCategories'], 10, 2);
        }
    }

    private function initBlocks(): void
    {
        $allPostTypes = null;

        if (function_exists('get_post_types')) {
            $allPostTypes = array_keys(get_post_types(['public' => true], 'names'));
        }

        try {
            foreach (FileService::getCustomBlockFiles() as $classPath) {
                require_once $classPath;
            }

            foreach (ClassService::getAllCustomBlockClasses() as $blockClass) {
                if ($className = ClassService::getClassNameFromFullName($blockClass)) {
                    if (!str_starts_with($className, 'Abstract')) {
                        $class = new $blockClass();

                        if (function_exists('register_extended_field_group')) {
                            $category = null;

                            if ($class::$category != 'common') {
                                $category = $class::$category;
                            } elseif ($blockClass !== 'App\\Blocks\\' . ClassService::getClassNameFromFullName($blockClass)) {
                                $category = ClassService::getFolderNameFromFullName(
                                    fullName: $blockClass,
                                    replacements: [
                                        'app/blocks/' => '',
                                    ]
                                );
                            }

                            if (ClassService::isAcfInstalledAndEnabled()) {
                                register_extended_field_group([
                                    'key' => $class::$slug,
                                    'title' => $class::$title,
                                    'fields' => $class->getFields() ? iterator_to_array($class->getFields(), false) : [],
                                    'location' => [Location::where('block', 'acf/' . $class::$slug)],
                                ]);

                                $allowedPostTypes = null;

                                if (null !== $allPostTypes) {
                                    $allowedPostTypes = $allPostTypes;

                                    if (null !== $class->getPostTypes() && !empty($class->getPostTypes())) {
                                        $allowedPostTypes = $class->getPostTypes();
                                    }

                                    if (null !== $class->getExcludedPostTypes()) {
                                        // Remove excluded post types from allowed post types
                                        $allowedPostTypes = array_values(array_diff($allowedPostTypes, $class->getExcludedPostTypes()));
                                    }

                                    $isSiteEditor = (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], 'site-editor.php') !== false)
                                        || (isset($_SERVER['SCRIPT_NAME']) && strpos($_SERVER['SCRIPT_NAME'], 'site-editor.php') !== false);


                                    $allowedPostTypes = $isSiteEditor ? [] : $allowedPostTypes;
                                }

                                acf_register_block_type([
                                    'name' => $class::$slug,
                                    'title' => $class::$title,
                                    'category' => $category,
                                    'mode' => $class::$mode,
                                    'description' => $class::$description,
                                    'icon' => $class::$icon,
                                    'post_types' => $allowedPostTypes,
                                    'render_callback' => function ($block) use ($class, $category) {
                                        $template =
                                            'blocks/' . ($category ? $category . '/' : '') . str_replace('acf/', '', $block['name']);

                                        if (file_exists(get_template_directory() . '/resources/views/' . $template . '.blade.php')) {
                                            if (isset($block['data']['_is_preview'])) {
                                                echo "<img style='width:100%' src='" .
                                                    get_template_directory_uri() .
                                                    '/resources/images/admin/blocks/' .
                                                    $category .
                                                    '/' .
                                                    $class::$slug .
                                                    ".jpg' alt='Preview'>";
                                                return;
                                            }

                                            echo view('blocks/' . $category . '/' . str_replace('acf/', '', $block['name']), [
                                                'block' => $block,
                                                'fields' => get_fields(),
                                                'context' => $class->addToContext(),
                                            ]);
                                        } else {
                                            throw new SkipProviderException('Template not found: ' . $template . '.blade.php');
                                        }
                                    },
                                    'supports' => $class->getSupports(),
                                    'example' => $class->getExample(),
                                ]);
                            }

                            add_filter(sprintf('render_block_%s', $class->getFullName()), function ($blockContent) use ($class, $category) {
                                $class->renderBlockCallback();

                                return $blockContent;
                            });
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            throw new SkipProviderException($e->getMessage());
        }
    }

    public function unregisterBlocks($allowedBlocks, $here): array
    {
        try {
            // Get only declared classes starting by App\Blocks
            $classes = array_filter(get_declared_classes(), function ($class) {
                return str_starts_with($class, 'App\Blocks') && $class !== 'App\Blocks\AbstractBlock';
            });

            $blocks = array_values(
                array_map(function ($class) {
                    return 'acf/' . $class::$slug;
                }, $classes)
            );

            $blocks[] = 'core/block';

            return $blocks;
        } catch (\Exception $e) {
            throw new SkipProviderException($e->getMessage());
        }
    }

    public function registerCustomBlockCategories($categories, $post): array
    {
        try {
            $this->registerCategoriesFromCases(BlockCategoriesEnum::class, $categories);

            if (class_exists(self::THEME_BLOCK_CATEGORIES_CLASS)) {
                $this->registerCategoriesFromCases(self::THEME_BLOCK_CATEGORIES_CLASS, $categories);
            }

            return $categories;
        } catch (\Exception $e) {
            throw new SkipProviderException($e->getMessage());
        }
    }

    private function registerCategoriesFromCases($enum, &$categories): void
    {
        if (method_exists($enum, 'cases')) {
            $cases = $enum::cases();
            usort($cases, function ($a, $b) use ($enum) {
                $orderA = $enum::ASSOCIATIONS[$a->value]['order'] ?? PHP_INT_MAX;
                $orderB = $enum::ASSOCIATIONS[$b->value]['order'] ?? PHP_INT_MAX;
                return $orderA <=> $orderB;
            });

            foreach ($cases as $case) {
                $icon = 'admin-post';
                $title = $case->value;
                $order = PHP_INT_MAX;
                $association = null;

                if (defined($enum . '::ASSOCIATIONS')) {
                    if (isset($enum::ASSOCIATIONS[$case->value]) && ($association = $enum::ASSOCIATIONS[$case->value])) {
                        if (isset($association['title'], $association['icon'], $association['order'])) {
                            $title = $association['title'];
                            $icon = $association['icon'];
                            $order = $association['order'];
                        }
                    }
                }

                $categories[] = [
                    'slug' => $case->value,
                    'title' => $title,
                    'icon' => $icon,
                    'order' => $order,
                ];
            }
        }
    }
}