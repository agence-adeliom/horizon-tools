<?php

declare(strict_types=1);

namespace Adeliom\SageTools\Console\Commands;

use Illuminate\Console\Command;
use Adeliom\SageTools\Services\ClassService;
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

	public function handle()
	{
		$path = $this->getPath();
		$folders = explode('/', $this->argument('name'));
		$className = last($folders);
		array_pop($folders);

		$filepath = $path . $this->argument('name') . '.php';

		if (file_exists($filepath)) {
			$this->error('Taxonomy already exists!');
			return;
		}

		if (!file_exists($path)) {
			mkdir($path, 0755, true);
		}

		foreach ($folders as $folder) {
			$path .= $folder . '/';
			if (!file_exists($path)) {
				mkdir($path, 0755, true);
			}
		}

		// Create slug from $className
		$slug = ClassService::slugifyClassName($className);

		$namespaceEnd = implode('\\', $folders);

		// Create empty file
		file_put_contents($filepath, str_replace([
			'%%NAMESPACE%%',
			'%%CLASS%%',
			'%%PARENT_NAMESPACE%%',
			'%%PARENT%%',
			'%%SLUG%%',
			'%%TAXONOMY_NAME%%',
		], [
			'App\Taxonomies' . ($namespaceEnd ? '\\' . $namespaceEnd : ''),
			$className,
			AbstractTaxonomy::class,
			ClassService::getClassNameFromFullName(AbstractTaxonomy::class),
			$slug,
			$className,
		], $this->getTemplate()));

		$this->info('Taxonomy created successfully at ' . $filepath);
	}
}