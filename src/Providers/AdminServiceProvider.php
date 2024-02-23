<?php

declare(strict_types=1);

namespace LucasVigneron\SageTools\Providers;

use LucasVigneron\SageTools\Admin\AbstractAdmin;
use LucasVigneron\SageTools\Services\ClassService;
use LucasVigneron\SageTools\Services\FileService;
use Roots\Acorn\Sage\SageServiceProvider;

class AdminServiceProvider extends SageServiceProvider
{
	public function boot(): void
	{
		$this->initAdmins();
		$this->moveMenuFromAppearance();
	}

	private function initAdmins(): void
	{
		$classes = get_declared_classes();

		foreach (FileService::getClassesPathsFromPath(get_template_directory() . '/app/Admin') as $classPath) {
			require_once $classPath;
		}

		$adminClasses = array_values(array_diff(get_declared_classes(), $classes));

		foreach ($adminClasses as $adminClass) {
			if ($className = ClassService::getClassNameFromFullName($adminClass)) {
				if (!str_starts_with($className, 'Abstract')) {
					$class = new $adminClass();

					if (is_subclass_of($class, AbstractAdmin::class)) {
						if (function_exists('register_extended_field_group')) {
							if ($fields = $class->getFields()) {
								if ($customFields = iterator_to_array($fields, false)) {
									register_extended_field_group([
										'title' => $class::$title,
										'fields' => $customFields,
										'style' => 'default',
										'location' => iterator_to_array($class->getLocation(), false),
									]);
								}
							}

							if ($class->isOptionPage()) {
								acf_add_options_page($class->getOptionPageParams());
							}
						}
					}
				}
			}
		}
	}

	private function moveMenuFromAppearance(): void
	{
		add_action('admin_menu', function () {
			remove_submenu_page('themes.php', 'nav-menus.php');

			add_menu_page(
				'Menus',
				'Menus',
				'edit_theme_options',
				'nav-menus.php',
				'',
				'dashicons-list-view',
				68
			);
		});
	}
}
