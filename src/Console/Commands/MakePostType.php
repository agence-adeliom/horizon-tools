<?php

declare(strict_types=1);

namespace Adeliom\HorizonTools\Console\Commands;

use Illuminate\Console\Command;
use Adeliom\HorizonTools\PostTypes\AbstractPostType;
use Adeliom\HorizonTools\Services\CommandService;

class MakePostType extends Command
{
	protected $signature = 'make:posttype {name?}';
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

	public function handle(): void
	{
		$path = $this->getPath();
		$name = $this->argument('name');

		while (null === $name) {
			$name = $this->ask('What is the relative path of the post-type? (Folder/Of/My/PostTypeFile)');
		}

		$structure = CommandService::getFolderStructure($name);
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