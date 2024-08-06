<?php

declare(strict_types=1);

namespace Adeliom\HorizonTools\Http\Middleware;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TrailingSlashMiddleware
{
	public function handle(Request $request, \Closure $next)
	{
		$path = $request->getPathInfo();

		// If last char is not a /, redirect to the same url with a /
		if (!str_ends_with($path, '/')) {
			return new RedirectResponse($request->getUri() . '/');
		}

		return $next($request);
	}
}
