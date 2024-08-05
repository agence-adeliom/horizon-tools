<?php

declare(strict_types=1);

namespace Adeliom\SageTools\Console\Commands;

use Illuminate\Console\Command;
use Adeliom\SageTools\Hooks\AbstractHook;
use Adeliom\SageTools\Services\CommandService;

class MakeHook extends Command
{
	protected $signature = 'make:hook {name}';
	protected $description = 'Create a new hook';

	public function getPath(): string
	{
		return get_template_directory() . '/app/Hooks/';
	}

	public function getTemplate(): string
	{
		$path = __DIR__ . '/../stubs/hook.stub';
		return file_exists($path) ? file_get_contents($path) : '';
	}

	public function handle(): void
	{
		$path = $this->getPath();

		$structure = CommandService::getFolderStructure($this->argument('name'));
		$folders = $structure['folders'];
		$className = $structure['class'];

		$filepath = $path . $structure['path'];

		$result = CommandService::handleClassCreation(AbstractHook::class, $filepath, $path, $folders, $className, $this->getTemplate());

		switch ($result) {
			case 'already_exists':
				$this->error('Hook already exists!');
				break;
			case 'success':
				$this->info('Hook created successfully at ' . $filepath);
				break;
		}
	}
}