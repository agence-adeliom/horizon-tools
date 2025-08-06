<?php

declare(strict_types=1);

namespace Adeliom\HorizonTools\Services\Compilation;

use Adeliom\HorizonTools\Services\Interfaces\CompilatorServiceInterface;
use Adeliom\HorizonTools\Services\VersionService;

class CompilationService implements CompilatorServiceInterface
{
    public static function shouldUseVite(): bool
    {
        return VersionService::getAcornVersion() === 5;
    }

    public static function shouldUseBud(): bool
    {
        return VersionService::getAcornVersion() === 4;
    }

    public static function getPath(string $handle): string
    {
        return match (true) {
            self::shouldUseVite() => ViteService::getPath(handle: $handle),
            default => BudService::getPath(handle: $handle),
        };
    }

    public static function getUrl(string $handle): false|string
    {
        return match (true) {
            self::shouldUseVite() => ViteService::getUrl(handle: $handle),
            default => BudService::getUrl(handle: $handle),
        };
    }

    public static function getUrlByRegex(string $regex): false|string
    {
        return match (true) {
            self::shouldUseVite() => ViteService::getUrlByRegex(regex: $regex),
            default => BudService::getUrlByRegex($regex),
        };
    }
}
