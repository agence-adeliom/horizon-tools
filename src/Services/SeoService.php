<?php

declare(strict_types=1);

namespace Adeliom\HorizonTools\Services;

class SeoService
{
	public static function isRankMathActive(): bool
	{
		if (function_exists('is_plugin_active')) {
			return is_plugin_active('seo-by-rank-math/rank-math.php');
		}

		return false;
	}
}