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

	public static function slugifyClassName(string $className): string
	{
		return strtolower(preg_replace('/(?<!^)[A-Z]/', '-$0', $className));
	}
}
