<?php

declare(strict_types=1);

namespace Adeliom\HorizonTools\Services;

use Composer\InstalledVersions;

class VersionService
{
    public static function getPackageVersion(string $packageName, bool $major = true): null|int|float
    {
        $version = null;

        if (class_exists(InstalledVersions::class) && InstalledVersions::isInstalled($packageName)) {
            $version = InstalledVersions::getVersion($packageName);

            if ($major) {
                $firstChar = substr($version, 0, 1);

                if (is_numeric($firstChar)) {
                    $version = intval($firstChar);
                }
            }
        }

        return $version;
    }

    public static function getAcornVersion(bool $major = true): null|int|float
    {
        return self::getPackageVersion(packageName: 'roots/acorn', major: $major);
    }
}
