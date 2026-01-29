<?php

declare(strict_types=1);

namespace Adeliom\HorizonTools\Providers;

use Adeliom\HorizonTools\Services\ClassService;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Roots\Acorn\Sage\SageServiceProvider;
use function Roots\add_filters;

class LivewireServiceProvider extends SageServiceProvider
{
    public function boot(): void
    {
        add_filter('wp_head', [$this, 'addLivewireStyle']);
        add_filter('wp_footer', [$this, 'addLivewireScript']);

        if (class_exists(Livewire::class)) {
            Livewire::setUpdateRoute(function ($handle) {
                return Route::post('/livewire/update', $handle);
            });

            Livewire::setScriptRoute(function ($handle) {
                return Route::get('/livewire/livewire.js', $handle);
            });
        }
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