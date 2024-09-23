<?php

declare(strict_types=1);

namespace Adeliom\HorizonTools\Console\Commands;

use Adeliom\HorizonTools\Services\AcfService;
use Adeliom\HorizonTools\Services\ClassService;
use Illuminate\Console\Command;
use Adeliom\HorizonTools\Admin\AbstractAdmin;
use Adeliom\HorizonTools\Services\CommandService;

class MakeAdmin extends Command
{
    protected $signature = 'make:admin {name?}';
    protected $description = 'Create a new admin fields group, or an option page';

    private bool $isOptionPage = false;
    private ?string $optionPageParent = null;
    private ?string $optionPageParentPath = null;

    private const TYPE_OPTION_PAGE = 'option';
    private const TYPE_POST_TYPE = 'cpt';

    private const PARENT_TYPES = [
        self::TYPE_OPTION_PAGE => 'Option Page',
        self::TYPE_POST_TYPE => 'Post Type',
    ];

    public function getPath(): string
    {
        return get_template_directory() . '/app/Admin/';
    }

    public function getTemplate(): string
    {
        $path = match ($this->isOptionPage) {
            true => __DIR__ . '/../stubs/admin-option.stub',
            default => __DIR__ . '/../stubs/admin.stub',
        };

        return file_exists($path) ? file_get_contents($path) : '';
    }

    public function handle(): void
    {
        $name = $this->argument('name');

        while (null === $name) {
            $name = $this->ask('What is the relative path of the admin? (Folder/Of/My/AdminFile)');
        }

        $path = $this->getPath();

        if ($this->confirm('Is this an option page?')) {
            $this->isOptionPage = true;

            if ($this->confirm('Do you want your option page to have a parent?')) {
                $existingOptionPages = ClassService::getAllCustomOptionPages(onlyRoot: true);
                $parentTypes = self::PARENT_TYPES;
                if (empty($existingOptionPages)) {
                    unset($parentTypes[self::TYPE_OPTION_PAGE]);
                }
                $parentType = $this->choice('Choose a parent type', self::PARENT_TYPES);

                switch ($parentType) {
                    case self::TYPE_OPTION_PAGE:
                        $parent = $this->choice(
                            'Choose a parent option page (only pages without parents are displayed)',
                            $existingOptionPages
                        );
                        $parentInstance = new $parent();

                        if (method_exists($parentInstance, 'getSlug')) {
                            if ($slug = $parentInstance->getSlug()) {
                                $this->optionPageParent = $parent;
                            }
                        }
                        break;
                    case self::TYPE_POST_TYPE:
                        $cpt = CommandService::choosePostType($this, 'Choose a parent post-type');

                        switch ($cpt) {
                            case CommandService::POST_TYPE_POST:
                                $urlPath = 'edit.php';
                                break;
                            case CommandService::POST_TYPE_PAGE:
                                $urlPath = 'edit.php?post_type=page';
                                break;
                            default:
                                if (isset($cpt::$slug)) {
                                    $urlPath = sprintf('edit.php?post_type=%s', $cpt::$slug);
                                }
                                break;
                        }

                        $this->optionPageParentPath = $urlPath;
                        break;
                    default:
                        break;
                }
            }
        }

        $structure = CommandService::getFolderStructure($name);
        $folders = $structure['folders'];
        $className = $structure['class'];

        $filepath = $path . $structure['path'];

        $result = CommandService::handleClassCreation(
            type: AbstractAdmin::class,
            filepath: $filepath,
            path: $path,
            folders: $folders,
            className: $className,
            template: $this->getTemplate(),
            parentClass: $this->optionPageParent,
            parentPath: $this->optionPageParentPath
        );

        switch ($result) {
            case 'already_exists':
                $this->error('Admin already exists!');
                break;
            case 'success':
                $this->info('Admin created successfully at ' . $filepath);
                break;
        }
    }
}
