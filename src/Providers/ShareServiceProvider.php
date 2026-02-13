<?php

declare(strict_types=1);

namespace Adeliom\HorizonTools\Providers;

use Adeliom\HorizonTools\Admin\ShareOptionsAdmin;
use Illuminate\Support\Facades\Config;
use Roots\Acorn\Sage\SageServiceProvider;

class ShareServiceProvider extends SageServiceProvider
{
    public function boot(): void
    {
        if (Config::get('share.enable', false)) {
            AdminServiceProvider::registerAdminByClass(adminClass: ShareOptionsAdmin::class);
        }
    }
}
