<?php

declare(strict_types=1);

namespace Adeliom\HorizonTools\Console\Commands;

use Illuminate\Console\Command;
use Adeliom\HorizonTools\Services\ClassService;

class ListPostTypes extends Command
{
    protected $signature = 'list:posttypes';
    protected $description = 'List all custom post-types';

    public function handle()
    {
        $header = ['Name', 'Slug', 'Class'];

        $data = [];

        foreach (ClassService::getAllCustomPostTypeClasses() as $postTypeClass) {
            $slug = $postTypeClass::$slug;
            $name = (new $postTypeClass())->getConfig()['args']['label'];

            $data[] = [$name, $slug, $postTypeClass];
        }

        $this->table($header, $data);

        return;
    }
}
