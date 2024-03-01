<?php

declare(strict_types=1);

namespace LucasVigneron\SageTools\Providers;

use Roots\Acorn\Exceptions\SkipProviderException;
use Roots\Acorn\Sage\SageServiceProvider;
use Symfony\Component\HttpFoundation\Request;

class MiddlewareServiceProvider extends SageServiceProvider
{
	public function boot(): void
	{
		$middlewaresConfig = config('middlewares');

		if (!$middlewaresConfig) {
			$middlewaresConfig = config('middleware');
		}

		if (null !== $middlewaresConfig && isset($middlewaresConfig['middlewares'])) {
			foreach ($middlewaresConfig['middlewares'] as $middleware) {
				$instance = new $middleware();

				if (method_exists($instance, 'handle')) {
					$instance->handle(new Request());
				} else {
					throw new SkipProviderException($middleware . ' : You must define a handle method for your middleware');
				}
			}
		}
	}
}