<?php

declare(strict_types=1);

namespace LucasVigneron\SageTools\Services;

use LucasVigneron\SageTools\Http\Request\Request;

class PaginationService
{
	public static function getPaginationData(Request $request, int $count): array
	{
		$pages = 1;

		if ($totalPages = ceil($count / $request->getPerPage())) {
			if ($totalPages > 1) {
				$pages = intval($totalPages);
			}
		}

		return [
			'current' => $request->getPage(),
			'count' => $count,
			'pages' => $pages
		];
	}
}