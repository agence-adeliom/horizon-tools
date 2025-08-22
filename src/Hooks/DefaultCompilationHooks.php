<?php

declare(strict_types=1);

namespace Adeliom\HorizonTools\Hooks;

use Adeliom\HorizonTools\ViewModels\Asset\AssetViewModel;

class DefaultCompilationHooks extends AbstractHook
{
    public function init(): void
    {
        add_filter('script_loader_tag', [$this, 'handleTag'], 10, 2);
    }

    public function handleTag(string $tag, string $handle)
    {
        if (str_starts_with($handle, AssetViewModel::HANDLE_PREFIX_VITE)) {
            $tag = str_replace('src=', 'type="module" src=', $tag);
        }

        return $tag;
    }
}
