<?php

declare(strict_types=1);

namespace Adeliom\HorizonTools\Console\Commands;

use Adeliom\HorizonTools\Services\AcfService;
use Adeliom\HorizonTools\Services\ClassService;
use Illuminate\Console\Command;
use Adeliom\HorizonTools\Admin\AbstractAdmin;
use Adeliom\HorizonTools\Services\CommandService;

class MakeAdmin extends Command
{
	protected $signature = 'make:admin {name}';
	protected $description = 'Create a new admin fields group';

	private bool $isOptionPage = false;
	private ?string $optionPageParent = null;

	private const TYPE_OPTION_PAGE = 'option';
	private const TYPE_POST_TYPE = 'cpt';

	private const PARENT_TYPES = [
		self::TYPE_OPTION_PAGE => 'Option Page',
		self::TYPE_POST_TYPE => 'Post Type',
	];

	public function getPath(): string
	{
		return get_template_directory() . '/app/Admin/';
	}

	public function getTemplate(): string
	{
		$path = match ($this->isOptionPage) {
			true => __DIR__ . '/../stubs/admin-option.stub',
			default => __DIR__ . '/../stubs/admin.stub',
		};

		return file_exists($path) ? file_get_contents($path) : '';
	}

	public function handle(): void
	{
		$path = $this->getPath();

		if ($this->confirm('Is this an option page?')) {
			$this->isOptionPage = true;

			if ($existingOptionPages = ClassService::getAllCustomOptionPages()) {
				if ($this->confirm('Do you want your option page to have a parent?')) {
					$parentType = $this->choice('Choose a parent type', self::PARENT_TYPES);

					switch ($parentType) {
						case self::TYPE_OPTION_PAGE:
							$parent = $this->choice('Choose a parent option page', $existingOptionPages);
							$parentInstance = new $parent();

							if (method_exists($parentInstance, 'getSlug')) {
								if ($slug = $parentInstance->getSlug()) {
									$this->optionPageParent = $parent;
								}
							}
							break;
						case self::TYPE_POST_TYPE:
							dd('choose cpt');
							break;
						default:
							break;
					}
				}
			}
		}

		$structure = CommandService::getFolderStructure($this->argument('name'));
		$folders = $structure['folders'];
		$className = $structure['class'];

		$filepath = $path . $structure['path'];

		$result = CommandService::handleClassCreation(AbstractAdmin::class, $filepath, $path, $folders, $className, $this->getTemplate(), parentClass: $this->optionPageParent);

		switch ($result) {
			case 'already_exists':
				$this->error('Admin already exists!');
				break;
			case 'success':
				$this->info('Admin created successfully at ' . $filepath);
				break;
		}
	}
}