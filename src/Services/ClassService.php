<?php

declare(strict_types=1);

namespace LucasVigneron\SageTools\Services;

class ClassService
{
	public static function getClassNameFromFullName(string $fullName): ?string
	{
		preg_match("/\\\\([^\\\\]+)$/", $fullName, $m);

		if (isset($m[1])) {
			return $m[1];
		}

		return null;
	}

	public static function getFolderNameFromFullName(string $fullName): ?string
	{
		$string = explode('\\', $fullName);
		return strtolower($string[count($string) - 2]);
	}
}
