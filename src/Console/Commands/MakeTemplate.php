<?php

declare(strict_types=1);

namespace Adeliom\HorizonTools\Console\Commands;

use Adeliom\HorizonTools\Services\ClassService;
use Illuminate\Console\Command;
use Adeliom\HorizonTools\Services\CommandService;
use Adeliom\HorizonTools\Templates\AbstractTemplate;

class MakeTemplate extends Command
{
	protected $signature = 'make:template {name?}';
	protected $description = 'Create a new post-type template';

	private const POST_CPT = 'Posts';
	private const PAGE_CPT = 'Pages';

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
		$postTypes = '[]';

		while (null === $name) {
			$name = $this->ask('What is the relative path of the template? (Folder/Of/My/TemplateFile)');
		}

		if ($this->confirm('Do you want to automatically link with an existing Post-Type?')) {
			$cpts = array_merge([
				self::POST_CPT,
				self::PAGE_CPT,
			], ClassService::getAllCustomPostTypeClasses());

			$cpt = $this->choice('Choose a post type', $cpts);

			switch ($cpt) {
				case self::POST_CPT:
					$postTypes = sprintf("['%s']", 'post');
					break;
				case self::PAGE_CPT:
					$postTypes = sprintf("['%s']", 'page');
					break;
				default:
					$postTypes = sprintf('[\%s::$slug]', $cpt);
					break;
			}
		}

		$structure = CommandService::getFolderStructure($name);
		$folders = $structure['folders'];
		$className = $structure['class'];

		$filepath = $path . $structure['path'];

		$result = CommandService::handleClassCreation(type: AbstractTemplate::class, filepath: $filepath, path: $path, folders: $folders, className: $className, template: $this->getTemplate(), postTypes: $postTypes);

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