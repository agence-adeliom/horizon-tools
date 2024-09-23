<?php

declare(strict_types=1);

namespace Adeliom\HorizonTools\Console\Commands;

use Adeliom\HorizonTools\Services\ClassService;
use Illuminate\Console\Command;
use Adeliom\HorizonTools\Services\CommandService;
use Adeliom\HorizonTools\Taxonomies\AbstractTaxonomy;

class MakeTaxonomy extends Command
{
    protected $signature = 'make:taxonomy {name?}';
    protected $description = 'Create a new taxonomy';

    public function getPath(): string
    {
        return get_template_directory() . '/app/Taxonomies/';
    }

    public function getTemplate(): string
    {
        $path = __DIR__ . '/../stubs/taxonomy.stub';
        return file_exists($path) ? file_get_contents($path) : '';
    }

    public function handle(): void
    {
        $path = $this->getPath();
        $name = $this->argument('name');
        $postTypes = '[]';
        $visibleInQuickEdit = true;
        $visibleInPost = true;

        while (null === $name) {
            $name = $this->ask('What is the relative path of the taxonomy? (Folder/Of/My/TaxonomyFile)');
        }

        if ($this->confirm('Do you want to automatically link with an existing Post-Type?')) {
            $cpt = CommandService::choosePostType($this);

            $postTypes = match ($cpt) {
                CommandService::POST_TYPE_POST => sprintf("['%s']", 'post'),
                CommandService::POST_TYPE_PAGE => sprintf("['%s']", 'page'),
                default => sprintf('[\%s::$slug]', $cpt),
            };
        }

        $visibleInQuickEdit = $this->confirm('Do you want to make the taxonomy visible in quick edit?', default: true);
        $visibleInPost = $this->confirm('Do you want to make the taxonomy visible in post?', default: true);

        $structure = CommandService::getFolderStructure($name);
        $folders = $structure['folders'];
        $className = $structure['class'];

        $filepath = $path . $structure['path'];

        $result = CommandService::handleClassCreation(
            type: AbstractTaxonomy::class,
            filepath: $filepath,
            path: $path,
            folders: $folders,
            className: $className,
            template: $this->getTemplate(),
            postTypes: $postTypes,
            taxonomyVisibleInQuickEdit: $visibleInQuickEdit,
            taxonomyVisibleInPost: $visibleInPost
        );

        switch ($result) {
            case 'already_exists':
                $this->error('Taxonomy already exists!');
                break;
            case 'success':
                $this->info('Taxonomy created successfully at ' . $filepath);
                break;
        }
    }
}
