<?php

declare(strict_types=1);

namespace Adeliom\HorizonTools\Console\Commands;

use Illuminate\Console\Command;
use Adeliom\HorizonTools\Services\ClassService;

class ListBlocks extends Command
{
    protected $signature = 'list:blocks';
    protected $description = 'List all custom blocks';

    public function handle()
    {
        $header = ['Name', 'Slug', 'Class'];

        $data = [];

        foreach (ClassService::getAllCustomBlockClasses() as $blockClass) {
            $slug = $blockClass::$slug;
            // getTitle() and not the $title property: blocks are free to override the getter to
            // return a translated title, and AbstractBlock::getTitle() falls back to $title
            // anyway. Reading the property left every such block listed without a name.
            $title = $blockClass::getTitle();

            $data[] = [$title, $slug, $blockClass];
        }

        $this->table($header, $data);
    }
}
