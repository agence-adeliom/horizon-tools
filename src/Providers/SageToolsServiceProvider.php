<?php

declare(strict_types=1);

namespace Adeliom\SageTools\Providers;

use Roots\Acorn\Application;
use Roots\Acorn\Sage\SageServiceProvider;

class SageToolsServiceProvider extends SageServiceProvider
{
	public function __construct(Application $app)
	{
		parent::__construct($app);
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
	}
}