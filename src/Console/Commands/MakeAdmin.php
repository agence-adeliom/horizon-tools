<?php

declare(strict_types=1);

namespace Adeliom\SageTools\Console\Commands;

use Illuminate\Console\Command;
use Adeliom\SageTools\Admin\AbstractAdmin;
use Adeliom\SageTools\Services\CommandService;

class MakeAdmin extends Command
{
	protected $signature = 'make:admin {name}';
	protected $description = 'Create a new admin fields group';

	public function getPath(): string
	{
		return get_template_directory() . '/app/Admin/';
	}

	public function getTemplate(): string
	{
		$path = __DIR__ . '/../stubs/admin.stub';
		return file_exists($path) ? file_get_contents($path) : '';
	}

	public function handle(): void
	{
		$path = $this->getPath();

		$structure = CommandService::getFolderStructure($this->argument('name'));
		$folders = $structure['folders'];
		$className = $structure['class'];

		$filepath = $path . $structure['path'];

		$result = CommandService::handleClassCreation(AbstractAdmin::class, $filepath, $path, $folders, $className, $this->getTemplate());

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