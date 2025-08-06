<?php

namespace Adeliom\HorizonTools\Services\Interfaces;

use Adeliom\HorizonTools\ViewModels\Asset\AssetViewModel;

interface CompilatorServiceInterface
{
    static function getPath(string $handle): string;

    static function getUrl(string $handle): false|string;

    static function getUrlByRegex(string $regex): false|string;

    static function getAsset(string $hande): null|AssetViewModel;
}
