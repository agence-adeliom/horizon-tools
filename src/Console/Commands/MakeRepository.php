<?php

declare(strict_types=1);

namespace Adeliom\HorizonTools\Console\Commands;

use Adeliom\HorizonTools\Repositories\AbstractRepository;
use Adeliom\HorizonTools\Services\ClassService;
use Adeliom\HorizonTools\Services\CommandService;
use Illuminate\Console\Command;

class MakeRepository extends Command
{
    protected $signature = 'make:repository {name?}';
    protected $description = 'Create a new repository';

    public function getPath(): string
    {
        return get_template_directory() . '/app/Repositories/';
    }

    public function getTemplate(): string
    {
        $path = __DIR__ . '/../stubs/repository.stub';
        return file_exists($path) ? file_get_contents($path) : '';
    }

    public function handle(): void
    {
        $path = $this->getPath();
        $name = $this->argument('name');
        $cptSlug = null;
        $perPage = null;

        while (null === $name) {
            $name = $this->ask('What is the relative path of the repository? (Folder/Of/My/RepositoryFile)');
        }

        $cpt = CommandService::choosePostType($this, 'Choose a post-type associated to your new repository');

        switch ($cpt) {
            case CommandService::POST_TYPE_POST:
            case CommandService::POST_TYPE_PAGE:
                $cptSlug = sprintf("'%s'", $cpt);
                break;
            default:
                $cptSlug = sprintf('\%s::$slug', $cpt);
                break;
        }

        while (!is_numeric($perPage)) {
            $perPage = $this->ask('How many items per page?');
        }

        $perPage = (int) $perPage;

        $structure = CommandService::getFolderStructure($name);
        $folders = $structure['folders'];
        $className = $structure['class'];

        $filepath = $path . $structure['path'];

        $result = CommandService::handleClassCreation(
            type: AbstractRepository::class,
            filepath: $filepath,
            path: $path,
            folders: $folders,
            className: $className,
            template: $this->getTemplate(),
            slug: $cptSlug,
            perPage: $perPage
        );

        switch ($result) {
            case 'already_exists':
                $this->error('Repository already exists!');
                break;
            case 'success':
                $this->info('Repository created successfully at ' . $filepath);
                break;
        }
    }
}
