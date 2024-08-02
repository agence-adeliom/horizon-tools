<?php

declare(strict_types=1);

namespace Adeliom\SageTools\Console\Commands;

use Illuminate\Console\Command;
use Adeliom\SageTools\Services\CommandService;
use Adeliom\SageTools\Templates\AbstractTemplate;

class MakeTemplate extends Command
{
	protected $signature = 'make:template {name}';
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
		$path = $this->getPath();

		$structure = CommandService::getFolderStructure($this->argument('name'));
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