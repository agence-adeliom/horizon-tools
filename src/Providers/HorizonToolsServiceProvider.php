<?php

declare(strict_types=1);

namespace Adeliom\HorizonTools\Providers;

use Adeliom\HorizonBlocks\Providers\HorizonBlocksServiceProvider;
use Composer\InstalledVersions;
use Illuminate\Http\Request;
use Roots\Acorn\Application;
use Roots\Acorn\Exceptions\SkipProviderException;
use Roots\Acorn\Sage\SageServiceProvider;

class HorizonToolsServiceProvider extends SageServiceProvider
{
	public function __construct(Application $app)
	{
		parent::__construct($app);
	}

	public function register(): void
	{
		Request::macro('hasValidSignature', function () {
			return true;
		});
	}

	public function boot(): void
	{
		(new AdminServiceProvider($this->app))->boot();
		(new BlockServiceProvider($this->app))->boot();
		(new CommentsServiceProvider($this->app))->boot();
		(new PostTypeServiceProvider($this->app))->boot();
		(new HooksServiceProvider($this->app))->boot();
		(new MiddlewareServiceProvider($this->app))->boot();
		(new CommandsServiceProvider($this->app))->boot();
		(new HttpLoginServiceProvider($this->app))->boot();

		if (InstalledVersions::isInstalled('agence-adeliom/horizon-blocks')) {
			try {
				if (class_exists(HorizonBlocksServiceProvider::class)) {
					$test = new HorizonBlocksServiceProvider($this->app);
					$test->boot();
				}
			} catch (\Exception $e) {
				throw new SkipProviderException($e->getMessage());
			}
		}
	}
}