<?php

declare(strict_types=1);

namespace Adeliom\HorizonTools\Console\Commands;

use Illuminate\Console\Command;
use Adeliom\HorizonTools\Hooks\AbstractHook;
use Adeliom\HorizonTools\Services\CommandService;

class MakeHook extends Command
{
	protected $signature = 'make:hook {name?}';
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
		$name = $this->argument('name');
		$path = $this->getPath();

		while (null === $name) {
			$name = $this->ask('What is the relative path of the hook? (Folder/Of/My/HookFile)');
		}

		$structure = CommandService::getFolderStructure($name);
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