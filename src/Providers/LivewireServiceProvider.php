<?php

declare(strict_types=1);

namespace Adeliom\HorizonTools\Providers;

use Adeliom\HorizonTools\Services\ClassService;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Roots\Acorn\Sage\SageServiceProvider;
use function Roots\add_filters;

class LivewireServiceProvider extends SageServiceProvider
{
    public function boot(): void
    {
        add_filter('wp_head', [$this, 'addLivewireStyle']);
        add_filter('wp_footer', [$this, 'addLivewireScript']);

        // WP admin requests bypass Acorn's HTTP kernel (StartSession never runs),
        // so csrf_token() returns an empty string in that context. This route goes
        // through the web middleware group (session started), making the token valid.
        Route::get('/livewire/csrf-token', fn() => csrf_token())->middleware('web')->name('livewire.csrf-token');
    }

    public function addLivewireStyle(): void
    {
        if (ClassService::isLivewireInstalled()) {
            echo Blade::render('@livewireStyles');
        }
    }

    public function addLivewireScript(): void
    {
        if (ClassService::isLivewireInstalled()) {
            echo Blade::render('@livewireScripts');
        }
    }
}
