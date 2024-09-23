<?php

declare(strict_types=1);

namespace Adeliom\HorizonTools\Console\Commands;

use Adeliom\HorizonTools\Repositories\AbstractRepository;
use Adeliom\HorizonTools\Repositories\AbstractTaxonomyRepository;
use Adeliom\HorizonTools\Services\ClassService;
use Adeliom\HorizonTools\Services\CommandService;
use Illuminate\Console\Command;

class MakeRepository extends Command
{
    protected $signature = 'make:repository {name?}';
    protected $description = 'Create a new repository';

    public const CHOICE_POST_TYPE = 'Post-Type';
    public const CHOICE_TAXONOMY = 'Taxonomy';

    public function getCptPath(): string
    {
        return get_template_directory() . '/app/Repositories/';
    }

    public function getTaxoPath(): string
    {
        return get_template_directory() . '/app/Repositories/Taxonomies/';
    }

    public function getCptTemplate(): string
    {
        $path = __DIR__ . '/../stubs/cpt-repository.stub';
        return file_exists($path) ? file_get_contents($path) : '';
    }

    public function getTaxoTemplate(): string
    {
        $path = __DIR__ . '/../stubs/taxo-repository.stub';
        return file_exists($path) ? file_get_contents($path) : '';
    }

    public function handle(): void
    {
        $path = null;
        $name = $this->argument('name');
        $perPage = null;
        $cpt = null;
        $taxonomy = null;
        $slug = null;

        while (null === $name) {
            $name = $this->ask('What is the relative path of the repository? (Folder/Of/My/RepositoryFile)');
        }

        $type = $this->choice(
            'Do you want to make a repository for a Post-Type or for a Taxonomy?',
            [self::CHOICE_POST_TYPE, self::CHOICE_TAXONOMY],
            default: 0
        );

        switch ($type) {
            case self::CHOICE_TAXONOMY:
                $path = $this->getTaxoPath();
                $taxonomy = CommandService::chooseTaxonomy($this, 'Choose a taxonomy associated to your new repository');
                $slug = sprintf('\%s::$slug', $taxonomy);
                break;
            case self::CHOICE_POST_TYPE:
            default:
                $path = $this->getCptPath();
                $cpt = CommandService::choosePostType($this, 'Choose a post-type associated to your new repository');

                $slug = match ($cpt) {
                    CommandService::POST_TYPE_POST, CommandService::POST_TYPE_PAGE => sprintf("'%s'", $cpt),
                    default => sprintf('\%s::$slug', $cpt),
                };
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
            type: $taxonomy !== null ? AbstractTaxonomyRepository::class : AbstractRepository::class,
            filepath: $filepath,
            path: $path,
            folders: $folders,
            className: $className,
            template: $taxonomy !== null ? $this->getTaxoTemplate() : $this->getCptTemplate(),
            slug: $slug,
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
