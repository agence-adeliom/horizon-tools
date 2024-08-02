<?php

declare(strict_types=1);

namespace Adeliom\SageTools\Console\Commands;

use Illuminate\Console\Command;
use Adeliom\SageTools\Services\CommandService;
use Adeliom\SageTools\Taxonomies\AbstractTaxonomy;

class MakeTaxonomy extends Command
{
	protected $signature = 'make:taxonomy {name}';
	protected $description = 'Create a new taxonomy';

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

		$structure = CommandService::getFolderStructure($this->argument('name'));
		$folders = $structure['folders'];
		$className = $structure['class'];

		$filepath = $path . $structure['path'];

		$result = CommandService::handleClassCreation(AbstractTaxonomy::class, $filepath, $path, $folders, $className, $this->getTemplate());

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