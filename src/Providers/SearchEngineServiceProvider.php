<?php

declare(strict_types=1);

namespace Adeliom\HorizonTools\Providers;

use Adeliom\HorizonTools\Admin\SearchEngineOptionsAdmin;
use Adeliom\HorizonTools\Services\SearchEngineService;
use Roots\Acorn\Sage\SageServiceProvider;

class SearchEngineServiceProvider extends SageServiceProvider
{
    public function boot(): void
    {
        if (SearchEngineService::isSearchEngineEnabled()) {
            AdminServiceProvider::registerAdminByClass(SearchEngineOptionsAdmin::class);
        }
    }
}
