<?php

declare(strict_types=1);

namespace Adeliom\HorizonTools\Services;

class EnvironmentService
{
    public static function isProduction(): bool
    {
        return env('WP_ENV') === 'production';
    }

    public static function isStaging(): bool
    {
        return env('WP_ENV') === 'staging';
    }

    public static function isDevelopment(): bool
    {
        return env('WP_ENV') === 'development';
    }
}
