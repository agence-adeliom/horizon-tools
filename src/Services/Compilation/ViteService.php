<?php

declare(strict_types=1);

namespace Adeliom\HorizonTools\Services\Compilation;

use Adeliom\HorizonTools\Services\Interfaces\CompilatorServiceInterface;
use Adeliom\HorizonTools\Services\Traits\CompilatorServiceTrait;
use Adeliom\HorizonTools\ViewModels\Asset\AssetViewModel;

class ViteService implements CompilatorServiceInterface
{
    use CompilatorServiceTrait;

    public static function getUrl(string $handle): false|string
    {
        if ($path = self::getManifestAssociation($handle)) {
            return get_template_directory_uri() . $path;
        } else {
            return false;
        }
    }

    public static function getPath(string $handle): string
    {
        if ($path = self::getManifestAssociation($handle)) {
            return '/app/themes/' . self::getTemplateName() . $path;
        } else {
            return '';
        }
    }

    public static function getUrlByRegex(string $regex): false|string
    {
        return '';
    }

    public static function getAsset(string $handle): null|AssetViewModel
    {
        if ($assetData = self::getManifestAssociation($handle, returnViteArray: true)) {
            return new AssetViewModel(file: $assetData['file']);
        }

        return null;
    }
}
