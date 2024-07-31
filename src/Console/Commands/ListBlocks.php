<?php

declare(strict_types=1);

namespace Adeliom\SageTools\Console\Commands;

use Illuminate\Console\Command;
use Adeliom\SageTools\Services\ClassService;

class ListBlocks extends Command
{
	protected $signature = 'list:blocks';
	protected $description = 'List all custom blocks';

	public function handle()
	{
		$header = [
			'Name',
			'Slug',
			'Class',
		];

		$data = [];

		foreach (ClassService::getAllCustomBlockClasses() as $blockClass) {
			$slug = $blockClass::$slug;
			$title = $blockClass::$title;

			$data[] = [
				$title,
				$slug,
				$blockClass,
			];
		}

		$this->table($header, $data);
	}
}