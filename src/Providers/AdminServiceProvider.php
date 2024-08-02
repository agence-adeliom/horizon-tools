<?php

declare(strict_types=1);

namespace Adeliom\SageTools\Providers;

use Adeliom\SageTools\Admin\AbstractAdmin;
use Adeliom\SageTools\Services\ClassService;
use Adeliom\SageTools\Services\FileService;
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
		foreach (FileService::getClassesPathsFromPath(get_template_directory() . '/app/Admin') as $classPath) {
			require_once $classPath;
		}

		$adminClasses = array_filter(get_declared_classes(), function ($class) {
			return is_subclass_of($class, AbstractAdmin::class);
		});

		foreach ($adminClasses as $adminClass) {
			if ($className = ClassService::getClassNameFromFullName($adminClass)) {
				if (!str_starts_with($className, 'Abstract')) {
					$class = new $adminClass();

					if (function_exists('register_extended_field_group')) {
						if ($fields = $class->getFields()) {
							if ($customFields = iterator_to_array($fields, false)) {
								register_extended_field_group([
									'title' => $class::$title,
									'fields' => $customFields,
									'style' => $class->getStyle(),
									'location' => iterator_to_array($class->getLocation(), false),
									'position' => $class->getPosition(),
									'label_placement' => $class->getLabelPlacement(),
									'instruction_placement' => $class->getInstructionPlacement(),
									'hide_on_screen' => $class->getHideOnScreen(),
									'menu_order' => $class->getMenuOrder(),
								]);
							}
						}

						if ($class::$isOptionPage) {
							$params = $class->getOptionPageParams();

							if ($class::$optionPageIcon) {
								$params['icon_url'] = $class::$optionPageIcon;
							}

							acf_add_options_page($params);
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
