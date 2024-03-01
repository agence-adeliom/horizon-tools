<?php

declare(strict_types=1);

namespace LucasVigneron\SageTools\Services;

use LucasVigneron\SageTools\Http\Request\Request;

class PaginationService
{
	public static function getPaginationData(Request $request, int $count, ?string $path = null): array
	{
		$pages = 1;

		if ($totalPages = ceil($count / $request->getPerPage())) {
			if ($totalPages > 1) {
				$pages = intval($totalPages);
			}
		}

		$data = [
			'current' => $request->getPage(),
			'count' => $count,
			'pages' => $pages
		];

		if ($path) {
			$urls = [];

			for ($x = 1; $x <= $pages; $x++) {
				if ($x === $data['current']) {
					$urls[$x] = null;
				} else {
					if ($x > 1) {
						$urls[$x] = $path . '?pagination=' . $x;
					} else {
						$urls[$x] = $path;
					}
				}
			}

			$data['urls'] = $urls;
		}

		return $data;
	}
}