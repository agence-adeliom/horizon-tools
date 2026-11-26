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
        // Script Vite mis en file par AssetViewModel (marqueur), ou handle préfixé (serveur de développement, entrée avec imports,
        // contournement des thèmes).
        $isModule = (bool) wp_scripts()->get_data($handle, AssetViewModel::SCRIPT_DATA_MODULE);

        foreach ([AssetViewModel::HANDLE_PREFIX_VITE, AssetViewModel::HANDLE_PREFIX_MODULE] as $prefixThatAreModule) {
            if (str_starts_with($handle, $prefixThatAreModule)) {
                $isModule = true;
                break;
            }
        }

        if ($isModule && !str_contains($tag, 'type="module"')) {
            $tag = str_replace('src=', 'type="module" src=', $tag);
        }

        return $tag;
    }
}
