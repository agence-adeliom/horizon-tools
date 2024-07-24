<?php

declare(strict_types=1);

namespace LucasVigneron\SageTools\Console\Commands;

use Illuminate\Console\Command;
use LucasVigneron\SageTools\Services\ClassService;

class ListTaxonomies extends Command
{
	protected $signature = 'list:taxonomies';
	protected $description = 'List all custom taxonomies';

	public function handle()
	{
		$header = [
			'Name',
			'Slug',
			'Class',
			'PostTypes',
		];

		$data = [];

		foreach (ClassService::getAllCustomTaxonomyClasses() as $taxonomyClass) {
			$taxo = (new $taxonomyClass());

			$slug = $taxonomyClass::$slug;
			$name = $taxo->getConfig()['args']['label'];
			$postTypes = implode(', ', $taxo->getPostTypes());

			$data[] = [
				$name,
				$slug,
				$taxonomyClass,
				$postTypes,
			];
		}

		$this->table($header, $data);
	}
}