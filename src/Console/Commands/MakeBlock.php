<?php

declare(strict_types=1);

namespace LucasVigneron\SageTools\Console\Commands;

use Illuminate\Console\Command;
use LucasVigneron\SageTools\Blocks\AbstractBlock;
use LucasVigneron\SageTools\Services\ClassService;

class MakeBlock extends Command
{
	protected $signature = 'make:block {name}';
	protected $description = 'Create a new block';

	public function getPath(): string
	{
		return get_template_directory() . '/app/Blocks/';
	}

	public function getTemplatesPath(): string
	{
		return get_template_directory() . '/resources/views/blocks/';
	}

	public function getTemplate(): string
	{
		$path = __DIR__ . '/../stubs/block.stub';
		return file_exists($path) ? file_get_contents($path) : '';
	}

	public function getTemplateContent(): string
	{
		$path = __DIR__ . '/../stubs/block-template.stub';
		return file_exists($path) ? file_get_contents($path) : '';
	}

	public function handle()
	{
		$path = $this->getPath();
		$templatePath = $this->getTemplatesPath();
		$folders = explode('/', $this->argument('name'));
		$className = last($folders);
		array_pop($folders);

		$filepath = $path . $this->argument('name') . '.php';

		if (file_exists($filepath)) {
			$this->error('Block already exists!');
			return;
		}

		if (!file_exists($path)) {
			mkdir($path, 0755, true);
		}

		foreach ($folders as $folder) {
			$path .= $folder . '/';
			$templatePath .= strtolower($folder) . '/';

			if (!file_exists($path)) {
				mkdir($path, 0755, true);
			}

			if (!file_exists($templatePath)) {
				mkdir($templatePath, 0755, true);
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
		], [
			'App\Blocks' . ($namespaceEnd ? '\\' . $namespaceEnd : ''),
			$className,
			AbstractBlock::class,
			ClassService::getClassNameFromFullName(AbstractBlock::class),
			$slug,
		], $this->getTemplate()));

		file_put_contents($templatePath . $slug . '.blade.php', str_replace([
			'%%BLOCK_PATH%%',
			'%%TEMPLATE_PATH%%',
		], [
			$filepath,
			$templatePath . $slug . '.blade.php',
		], $this->getTemplateContent()));

		$this->info('Block created successfully at ' . $filepath);
		$this->info('Template created successfully at ' . $templatePath . $slug . '.blade.php');
	}
}