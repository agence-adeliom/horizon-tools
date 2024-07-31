<?php

declare(strict_types=1);

namespace Adeliom\SageTools\Console\Commands;

use Illuminate\Console\Command;
use Adeliom\SageTools\PostTypes\AbstractPostType;
use Adeliom\SageTools\Services\ClassService;

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
		$folders = explode('/', $this->argument('name'));
		$className = last($folders);
		array_pop($folders);

		$filepath = $path . $this->argument('name') . '.php';

		if (file_exists($filepath)) {
			$this->error('PostType already exists!');
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
			'%%CPT_NAME%%',
		], [
			'App\PostTypes' . ($namespaceEnd ? '\\' . $namespaceEnd : ''),
			$className,
			AbstractPostType::class,
			ClassService::getClassNameFromFullName(AbstractPostType::class),
			$slug,
			$className,
		], $this->getTemplate()));

		$this->info('PostType created successfully at ' . $filepath);
	}
}