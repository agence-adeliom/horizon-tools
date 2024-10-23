<?php

declare(strict_types=1);

namespace Adeliom\HorizonTools\Fields\Select;

use Adeliom\HorizonTools\Services\ClassService;
use Adeliom\HorizonTools\Services\FileService;
use Extended\ACF\Fields\Select;

class PostTypeSelectField
{
    final public const LABEL = 'Type de contenu';
    final public const NAME = 'postType';

    /**
     * @param string $label
     * @param string|null $name
     * @param array|null $excluded Array of post type slugs to exclude
     * @return Select
     */
    public static function make(
        string $label = self::LABEL,
        ?string $name = self::NAME,
        ?array $excluded = null,
        ?array $with = ['post'],
        ?callable $callback = null
    ): Select {
        $choices = [];

        if ($with) {
            foreach ($with as $item) {
                $choices[$item] = match ($item) {
                    'post' => __('Articles'),
                    'page' => __('Pages'),
                    default => ucfirst($item),
                };
            }
        }

        $classes = FileService::getClassesPathsFromPath(get_template_directory() . '/app/PostTypes');

        foreach ($classes as $class) {
            $className = ClassService::getClassNameFromFilePath($class);

            if (class_exists($className)) {
                $postType = new $className();

                $slug = $className::$slug;

                if ($config = $postType->getConfig()) {
                    if (null === $callback || $callback($postType)) {
                        if (isset($config['args']['label'])) {
                            $choices[$slug] = $config['args']['label'];
                        } else {
                            $choices[$slug] = $slug;
                        }
                    }
                }
            }
        }

        if (null !== $excluded) {
            $choices = array_diff_key($choices, array_flip($excluded));
        }

        return Select::make(__($label), $name)->choices($choices)->stylized();
    }
}
