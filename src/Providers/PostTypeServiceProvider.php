<?php

declare(strict_types=1);

namespace Adeliom\HorizonTools\Providers;

use Adeliom\HorizonTools\Blocks\CompositionBlock;
use Adeliom\HorizonTools\Database\MetaQuery;
use Adeliom\HorizonTools\PostTypes\AbstractPostType;
use Extended\ACF\Location;
use Adeliom\HorizonTools\Blocks\AbstractBlock;
use Adeliom\HorizonTools\Services\ClassService;
use Adeliom\HorizonTools\Services\FileService;
use Adeliom\HorizonTools\Taxonomies\AbstractTaxonomy;
use Illuminate\Support\Facades\Config;
use Roots\Acorn\Sage\SageServiceProvider;
use Roots\Acorn\Exceptions\SkipProviderException;

class PostTypeServiceProvider extends SageServiceProvider
{
    private ?array $templates = null;

    public const TEMPLATE_BLOCK_CLASS_KEY = 'blockClass';
    private const MOVE_DATE_COLUMN_AT_THE_END = true;

    public function boot(): void
    {
        add_filter('register_post_type_args', [$this, 'setTemplates'], accepted_args: 2);

        $this->initPostTypes();
        $this->initTaxonomies();
        $this->initListings();
    }

    public function getTemplates(): array
    {
        if (null === $this->templates) {
            $this->templates = $this->getTemplatesPerPostType();
        }

        return $this->templates;
    }

    public function setTemplates($args, $postType)
    {
        if (!isset($args['template'])) {
            if (isset($this->getTemplates()[$postType])) {
                $args['template'] = $this->getTemplates()[$postType];
            }
        }

        return $args;
    }

    private function getTemplatesPerPostType(): array
    {
        $data = [];
        foreach (FileService::getCustomTemplateFiles() as $classPath) {
            require_once $classPath;
        }

        foreach (ClassService::getAllCustomTemplateClasses() as $templateClass) {
            if ($className = ClassService::getClassNameFromFullName($templateClass)) {
                if (!str_starts_with($className, 'Abstract')) {
                    $class = new $templateClass();

                    if ($class->getPostTypes() && $class->getBlocks()) {
                        foreach ($class->getPostTypes() as $postType) {
                            $template = [];

                            foreach ($class->getBlocks() as $block => $fields) {
                                if (is_numeric($block) && isset($fields[self::TEMPLATE_BLOCK_CLASS_KEY])) {
                                    $block = $fields[self::TEMPLATE_BLOCK_CLASS_KEY];
                                }

                                if (is_string($block)) {
                                    $blockClass = new $block();

                                    if ($blockClass instanceof CompositionBlock) {
                                        if (isset($fields['id'])) {
                                            $template[] = ['core/block', ['ref' => $fields['id']]];
                                        }
                                    } elseif ($blockClass instanceof AbstractBlock) {
                                        $template[] = ['acf/' . $blockClass::$slug, $fields];
                                    }
                                }
                            }

                            $data[$postType] = $template;
                        }
                    }
                }
            }
        }

        return $data;
    }

    private function initPostTypes(): void
    {
        try {
            $templates = $this->getTemplatesPerPostType();

            foreach (FileService::getCustomPostTypeFiles() as $classPath) {
                require_once $classPath;
            }

            foreach (ClassService::getAllCustomPostTypeClasses() as $postTypeClass) {
                if ($className = ClassService::getClassNameFromFullName($postTypeClass)) {
                    if (!str_starts_with($className, 'Abstract')) {
                        $class = new $postTypeClass();

                        if ($config = $class->getConfig()) {
                            if (isset($config['post_type'])) {
                                register_post_type($config['post_type'], $config['args']);
                            }
                        }

                        if (ClassService::isAcfInstalledAndEnabled() && function_exists('register_extended_field_group')) {
                            if ($fields = $class->getFields()) {
                                if ($customFields = iterator_to_array($fields, false)) {
                                    register_extended_field_group([
                                        'key' => 'group_' . $class::$slug,
                                        'title' => $class->getFieldsTitle(),
                                        'fields' => $customFields,
                                        'style' => $class->getStyle(),
                                        'location' => [Location::where('post_type', $class::$slug)],
                                        'position' => $class->getPosition(),
                                        'label_placement' => $class->getLabelPlacement(),
                                        'instruction_placement' => $class->getInstructionPlacement(),
                                        'hide_on_screen' => $class->getHideOnScreen(),
                                        'menu_order' => $class->getMenuOrder(),
                                    ]);
                                }
                            }
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            throw new SkipProviderException($e->getMessage());
        }
    }

    private function initTaxonomies(): void
    {
        try {
            foreach (FileService::getCustomTaxonomyFiles() as $classPath) {
                require_once $classPath;
            }

            foreach (ClassService::getAllCustomTaxonomyClasses() as $taxonomyClass) {
                if ($className = ClassService::getClassNameFromFullName($taxonomyClass)) {
                    if (!str_starts_with($className, 'Abstract')) {
                        $class = new $taxonomyClass();

                        if (is_subclass_of($class, AbstractTaxonomy::class)) {
                            if ($config = $class->getConfig()) {
                                if (isset($config['taxonomy'])) {
                                    register_taxonomy($config['taxonomy'], $config['object_type'], $config['args']);
                                }
                            }

                            if (ClassService::isAcfInstalledAndEnabled() && function_exists('register_extended_field_group')) {
                                if ($class->getFields() && ($customFields = iterator_to_array($class->getFields(), false))) {
                                    register_extended_field_group([
                                        'key' => 'group_taxonomy_' . $class::$slug,
                                        'title' => $class->getFieldsTitle(),
                                        'fields' => $customFields,
                                        'style' => $class->getStyle(),
                                        'location' => [Location::where('taxonomy', $class::$slug)],
                                        'position' => $class->getPosition(),
                                        'label_placement' => $class->getLabelPlacement(),
                                        'instruction_placement' => $class->getInstructionPlacement(),
                                        'hide_on_screen' => $class->getHideOnScreen(),
                                        'menu_order' => $class->getMenuOrder(),
                                    ]);
                                }
                            }
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            throw new SkipProviderException($e->getMessage());
        }
    }

    public function initListings(): void
    {
        $postColumns = Config::get('posts.columns');

        $this->initCustomColumns($postColumns, 'post');

        foreach (ClassService::getAllCustomPostTypeClasses() as $postTypeClass) {
            $postTypeInstance = new $postTypeClass();

            if ($postTypeInstance instanceof AbstractPostType) {
                $this->initCustomColumns($postTypeInstance->getCustomColumns(), $postTypeInstance::$slug);
            }
        }
    }

    private function initCustomColumns(?array $customColumns, string $postTypeSlug)
    {
        if (!empty($customColumns)) {
            add_filter(
                sprintf('manage_%s_posts_columns', $postTypeSlug),
                fn($columns) => $this->handlePostTypeColumns($customColumns, $columns)
            );
            add_action(
                sprintf('manage_%s_posts_custom_column', $postTypeSlug),
                fn($column, $postId) => $this->handlePostTypeCustomColumnContent($customColumns, $column, $postId),
                10,
                2
            );
            add_filter(
                sprintf('manage_edit-%s_sortable_columns', $postTypeSlug),
                fn($columns) => $this->handlePostTypeCustomColumnSortable($customColumns, $columns)
            );
            add_action('pre_get_posts', function ($query) use ($customColumns) {
                $this->handleOrderByCustomColumn($customColumns, $query);
            });
        }
    }

    public function handlePostTypeColumns(?array $customColumns, $columns): array
    {
        $hasDateCustomColumn = false;

        if (null !== $customColumns) {
            foreach ($customColumns as $customColumn) {
                if (
                    !empty($customColumn[AbstractPostType::CUSTOM_COLUMN_LABEL]) &&
                    !empty($customColumn[AbstractPostType::CUSTOM_COLUMN_KEY])
                ) {
                    if (isset($columns[$customColumn[AbstractPostType::CUSTOM_COLUMN_KEY]])) {
                        unset($columns[$customColumn[AbstractPostType::CUSTOM_COLUMN_KEY]]);
                    }

                    if (
                        !isset($customColumn[AbstractPostType::CUSTOM_COLUMN_DISPLAY]) ||
                        $customColumn[AbstractPostType::CUSTOM_COLUMN_DISPLAY] === true
                    ) {
                        if (!$hasDateCustomColumn && $customColumn[AbstractPostType::CUSTOM_COLUMN_KEY] === 'date') {
                            $hasDateCustomColumn = true;
                        }

                        $columns[$customColumn[AbstractPostType::CUSTOM_COLUMN_KEY]] = $customColumn[AbstractPostType::CUSTOM_COLUMN_LABEL];
                    }
                }
            }
        }

        if (!$hasDateCustomColumn && !empty($columns['date']) && self::MOVE_DATE_COLUMN_AT_THE_END) {
            $tempDateColumn = $columns['date'];
            unset($columns['date']);

            $columns['date'] = $tempDateColumn;
        }

        return $columns;
    }

    public function handlePostTypeCustomColumnContent(?array $customColumns, string $columnName, int $postId): void
    {
        if (!empty($customColumns)) {
            $columnData = null;

            foreach ($customColumns as $column) {
                if (!empty($column[AbstractPostType::CUSTOM_COLUMN_KEY])) {
                    if ($column[AbstractPostType::CUSTOM_COLUMN_KEY] === $columnName) {
                        $columnData = $column;
                        break;
                    }
                }
            }

            if (is_array($columnData)) {
                if ($value = get_field($columnData[AbstractPostType::CUSTOM_COLUMN_KEY], $postId)) {
                    if (
                        !empty($columnData[AbstractPostType::CUSTOM_COLUMN_CALLBACK]) &&
                        is_callable($columnData[AbstractPostType::CUSTOM_COLUMN_CALLBACK])
                    ) {
                        $columnData[AbstractPostType::CUSTOM_COLUMN_CALLBACK]($value, $postId);
                    } else {
                        echo $value;
                    }
                }
            }
        }
    }

    public function handlePostTypeCustomColumnSortable(?array $customColumns, array $columns)
    {
        if (null !== $customColumns) {
            foreach ($customColumns as $customColumn) {
                if (
                    !empty($customColumn[AbstractPostType::CUSTOM_COLUMN_SORTABLE]) &&
                    $customColumn[AbstractPostType::CUSTOM_COLUMN_SORTABLE] === true
                ) {
                    if (!empty($customColumn[AbstractPostType::CUSTOM_COLUMN_KEY])) {
                        $columns[$customColumn[AbstractPostType::CUSTOM_COLUMN_KEY]] = [
                            $customColumn[AbstractPostType::CUSTOM_COLUMN_KEY],
                            false,
                            $customColumn[AbstractPostType::CUSTOM_COLUMN_LABEL] ?? 'Tri',
                            sprintf('Tableau trié par %s', $customColumn[AbstractPostType::CUSTOM_COLUMN_LABEL] ?? 'colonne'),
                        ];
                    }
                }
            }
        }

        return $columns;
    }

    public function handleOrderByCustomColumn(?array $customColumns, \WP_Query $query)
    {
        if (!is_admin() || !$query->is_main_query()) {
            return;
        }

        if ($orderBy = $query->get('orderby')) {
            $column = null;
            $isOrderByMeta = true;

            foreach ($customColumns as $customColumn) {
                if (!empty($customColumn[AbstractPostType::CUSTOM_COLUMN_KEY])) {
                    if ($customColumn[AbstractPostType::CUSTOM_COLUMN_KEY] === $orderBy) {
                        $column = $customColumn;
                        break;
                    }
                }
            }

            if (
                null !== $column &&
                !empty($column[AbstractPostType::CUSTOM_COLUMN_SORTABLE]) &&
                $column[AbstractPostType::CUSTOM_COLUMN_SORTABLE] === true
            ) {
                if ($isOrderByMeta) {
                    $query->set('orderby', 'meta_value');
                    $query->set('meta_key', $column[AbstractPostType::CUSTOM_COLUMN_KEY]);
                } else {
                    $query->set('orderby', $column[AbstractPostType::CUSTOM_COLUMN_KEY]);
                }
            }
        }
    }
}
