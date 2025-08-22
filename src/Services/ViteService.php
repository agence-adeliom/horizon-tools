<?php

declare(strict_types=1);

namespace Adeliom\HorizonTools\Services;

use Adeliom\HorizonTools\Services\Interfaces\CompilatorServiceInterface;
use Adeliom\HorizonTools\Services\Traits\CompilatorServiceTrait;
use Adeliom\HorizonTools\ViewModels\Asset\AssetViewModel;
use Illuminate\Support\Facades\Vite;

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
        $publicDirectory = self::getPublicDirectory();
        $cssDirectory = $publicDirectory . 'build/assets/';

        foreach (scandir($cssDirectory) as $fileName) {
            if (preg_match($regex, $fileName)) {
                return get_template_directory_uri() . self::getPublicDirectory(full: false) . 'build/assets/' . $fileName;
            }
        }

        return '';
    }

    public static function getAsset(string $handle): null|AssetViewModel
    {
        if (Vite::isRunningHot()) {
            if ($url = Vite::asset($handle)) {
                $file = parse_url($url)['path'] ?? null;

                return new AssetViewModel(file: $file, forceUrl: $url, isHot: true);
            }
        } elseif ($assetData = self::getManifestAssociation($handle, returnViteArray: true)) {
            return new AssetViewModel(file: $assetData['file']);
        }

        return null;
    }
}
