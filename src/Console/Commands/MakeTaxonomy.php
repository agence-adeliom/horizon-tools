<?php

declare(strict_types=1);

namespace Adeliom\HorizonTools\Console\Commands;

use Adeliom\HorizonTools\Services\ClassService;
use Illuminate\Console\Command;
use Adeliom\HorizonTools\Services\CommandService;
use Adeliom\HorizonTools\Taxonomies\AbstractTaxonomy;

class MakeTaxonomy extends Command
{
	protected $signature = 'make:taxonomy {name?}';
	protected $description = 'Create a new taxonomy';

	private const POST_CPT = 'Posts';
	private const PAGE_CPT = 'Pages';

	public function getPath(): string
	{
		return get_template_directory() . '/app/Taxonomies/';
	}

	public function getTemplate(): string
	{
		$path = __DIR__ . '/../stubs/taxonomy.stub';
		return file_exists($path) ? file_get_contents($path) : '';
	}

	public function handle(): void
	{
		$path = $this->getPath();
		$name = $this->argument('name');

		while (null === $name) {
			$name = $this->ask('What is the relative path of the taxonomy? (Folder/Of/My/TaxonomyFile)');
		}

		$postTypes = '[]';

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

		$result = CommandService::handleClassCreation(type: AbstractTaxonomy::class, filepath: $filepath, path: $path, folders: $folders, className: $className, template: $this->getTemplate(), postTypes: $postTypes);

		switch ($result) {
			case 'already_exists':
				$this->error('Taxonomy already exists!');
				break;
			case 'success':
				$this->info('Taxonomy created successfully at ' . $filepath);
				break;
		}
	}
}