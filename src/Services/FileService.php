<?php

declare(strict_types=1);

namespace Adeliom\HorizonTools\Services;

use FilesystemIterator;

class FileService
{
	public static function getClassesPathsFromPath(string $path): array
	{
		$classes = [];

		if (file_exists($path)) {
			$Directory = new \RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS);
			$Iterator = new \RecursiveIteratorIterator($Directory);
			$Regex = new \RegexIterator($Iterator, '/^.+\.php$/i', \RecursiveRegexIterator::GET_MATCH);

			foreach ($Regex as $file) {
				foreach ($file as $element) {
					if (str_ends_with(basename($element), '.php')) {
						$classes[] = $element;
					}
				}
			}

			// Sort classes by name
			usort($classes, function ($a, $b) {
				$nameA = basename($a);
				$nameB = basename($b);

				if ($nameA < $nameB) {
					return -1;
				} elseif ($nameA > $nameB) {
					return 1;
				}

				return 0;
			});
		}

		return $classes;
	}
}
