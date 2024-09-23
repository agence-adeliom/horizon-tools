<?php

declare(strict_types=1);

namespace Adeliom\HorizonTools\Console\Commands;

use Illuminate\Console\Command;
use Adeliom\HorizonTools\Blocks\AbstractBlock;
use Adeliom\HorizonTools\Services\ClassService;
use Adeliom\HorizonTools\Services\CommandService;

class MakeBlock extends Command
{
    protected $signature = 'make:block {name?}';
    protected $description = 'Create a new block';

    public function getPath(): string
    {
        return get_template_directory() . '/app/Blocks/';
    }

    public function getTemplatesPath(): string
    {
        return get_template_directory() . '/resources/views/blocks/';
    }

    public function getTemplate(): string
    {
        $path = __DIR__ . '/../stubs/block.stub';
        return file_exists($path) ? file_get_contents($path) : '';
    }

    public function getTemplateContent(): string
    {
        $path = __DIR__ . '/../stubs/block-template.stub';
        return file_exists($path) ? file_get_contents($path) : '';
    }

    private function handleClassName(string $className): string
    {
        if (str_ends_with($className, 'Block')) {
            $className = substr($className, 0, -5);
        }

        return $className;
    }

    public function handle(): void
    {
        $path = $this->getPath();
        $templatePath = $this->getTemplatesPath();
        $name = $this->argument('name');

        while (null === $name) {
            $name = $this->ask('What is the relative path of the block? (Folder/Of/My/BlockFile)');
        }

        $path = $this->getPath();

        $structure = CommandService::getFolderStructure($name);
        $folders = $structure['folders'];
        $className = $structure['class'];

        $filepath = $path . $structure['path'];

        $slug = ClassService::slugifyClassName($className);

        if (str_ends_with($slug, '-block')) {
            $slug = substr($slug, 0, -6);
        }

        $result = CommandService::handleClassCreation(
            type: AbstractBlock::class,
            filepath: $filepath,
            path: $path,
            folders: $folders,
            className: $className,
            template: $this->getTemplate(),
            slug: $slug
        );

        switch ($result) {
            case 'already_exists':
                $this->error('Block already exists!');
                return;
            case 'success':
                $this->info('Block created successfully at ' . $filepath);
                break;
        }

        foreach ($folders as $folder) {
            $templatePath .= strtolower($folder) . '/';

            if (!file_exists($templatePath)) {
                mkdir($templatePath, 0755, true);
            }
        }

        file_put_contents(
            $templatePath . $slug . '.blade.php',
            str_replace(
                ['%%BLOCK_PATH%%', '%%TEMPLATE_PATH%%'],
                [$filepath, $templatePath . $slug . '.blade.php'],
                $this->getTemplateContent()
            )
        );

        $this->info('Template created successfully at ' . $templatePath . $slug . '.blade.php');
    }
}
