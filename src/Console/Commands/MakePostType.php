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
		$supports = ['title', 'editor'];

		while (null === $name) {
			$name = $this->ask('What is the relative path of the post-type? (Folder/Of/My/PostTypeFile)');
		}

		if ($this->confirm('Do you want to configure the supported fields?')) {
			$supports = $this->choice('What fields the post-type should support? (separated by ,)', [
				'title',
				'editor',
				'thumbnail',
				'excerpt',
				'revisions',
				'author',
				'trackbacks',
				'comments',
			], multiple: true);
		}

		$structure = CommandService::getFolderStructure($name);
		$folders = $structure['folders'];
		$className = $structure['class'];

		$filepath = $path . $structure['path'];

		$result = CommandService::handleClassCreation(type: AbstractPostType::class, filepath: $filepath, path: $path, folders: $folders, className: $className, template: $this->getTemplate(), postTypeSupports: $supports);

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