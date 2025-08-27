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
        $prefixThatAreModules = [AssetViewModel::HANDLE_PREFIX_VITE, AssetViewModel::HANDLE_PREFIX_MODULE];

        foreach ($prefixThatAreModules as $prefixThatAreModule) {
            if (str_starts_with($handle, $prefixThatAreModule)) {
                $tag = str_replace('src=', 'type="module" src=', $tag);
                break;
            }
        }

        return $tag;
    }
}
