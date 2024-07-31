<?php

declare(strict_types=1);

namespace Adeliom\SageTools\Console\Commands;

use Illuminate\Console\Command;
use Adeliom\SageTools\Services\ClassService;

class ListPostTypes extends Command
{
	protected $signature = 'list:posttypes';
	protected $description = 'List all custom blocks';

	public function handle()
	{
		$header = [
			'Name',
			'Slug',
			'Class',
		];

		$data = [];

		foreach (ClassService::getAllCustomPostTypeClasses() as $postTypeClass) {
			$slug = $postTypeClass::$slug;
			$name = (new $postTypeClass())->getConfig()['args']['label'];

			$data[] = [
				$name,
				$slug,
				$postTypeClass,
			];
		}

		$this->table($header, $data);

		return;
	}
}