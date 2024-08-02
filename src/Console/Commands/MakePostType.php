<?php

declare(strict_types=1);

namespace Adeliom\SageTools\Console\Commands;

use Illuminate\Console\Command;
use Adeliom\SageTools\PostTypes\AbstractPostType;
use Adeliom\SageTools\Services\CommandService;

class MakePostType extends Command
{
	protected $signature = 'make:posttype {name}';
	protected $description = 'Create a new post-type';

	public function getPath(): string
	{
		return get_template_directory() . '/app/PostTypes/';
	}

	public function getTemplate(): string
	{
		$path = __DIR__ . '/../stubs/posttype.stub';
		return file_exists($path) ? file_get_contents($path) : '';
	}

	public function handle()
	{
		$path = $this->getPath();

		$structure = CommandService::getFolderStructure($this->argument('name'));
		$folders = $structure['folders'];
		$className = $structure['class'];

		$filepath = $path . $structure['path'];

		$result = CommandService::handleClassCreation(AbstractPostType::class, $filepath, $path, $folders, $className, $this->getTemplate());

		switch ($result) {
			case 'already_exists':
				$this->error('PostType already exists!');
				break;
			case 'success':
				$this->info('PostType created successfully at ' . $filepath);
				break;
		}
	}
}