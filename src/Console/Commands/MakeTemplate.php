<?php

declare(strict_types=1);

namespace Adeliom\HorizonTools\Console\Commands;

use Illuminate\Console\Command;
use Adeliom\HorizonTools\Services\CommandService;
use Adeliom\HorizonTools\Templates\AbstractTemplate;

class MakeTemplate extends Command
{
	protected $signature = 'make:template {name?}';
	protected $description = 'Create a new post-type template';

	public function getPath(): string
	{
		return get_template_directory() . '/app/Templates/';
	}

	public function getTemplate(): string
	{
		$path = __DIR__ . '/../stubs/template.stub';
		return file_exists($path) ? file_get_contents($path) : '';
	}

	public function handle(): void
	{
		$name = $this->argument('name');
		$path = $this->getPath();

		while (null === $name) {
			$name = $this->ask('What is the relative path of the template? (Folder/Of/My/TemplateFile)');
		}

		$structure = CommandService::getFolderStructure($name);
		$folders = $structure['folders'];
		$className = $structure['class'];

		$filepath = $path . $structure['path'];

		$result = CommandService::handleClassCreation(AbstractTemplate::class, $filepath, $path, $folders, $className, $this->getTemplate());

		switch ($result) {
			case 'already_exists':
				$this->error('Template already exists!');
				break;
			case 'success':
				$this->info('Template created successfully at ' . $filepath);
				break;
		}
	}
}