<?php

declare(strict_types=1);

namespace LucasVigneron\SageTools\Services;

class SeoService
{
	public static function isRankMathActive(): bool
	{
		return is_plugin_active('seo-by-rank-math/rank-math.php');
	}
}