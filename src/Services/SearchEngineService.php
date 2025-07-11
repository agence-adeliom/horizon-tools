<?php

declare(strict_types=1);

namespace Adeliom\HorizonTools\Services;

use Adeliom\HorizonTools\Admin\SearchEngineOptionsAdmin;
use Illuminate\Support\Facades\Config;

class SearchEngineService
{
    public static function isSearchEngineEnabled(): bool
    {
        return Config::get('search-engine.enabled', false);
    }

    public static function getSearchEngineConfig(): false|array
    {
        if (!self::isSearchEngineEnabled()) {
            return false;
        }

        return get_field(SearchEngineOptionsAdmin::FIELD_HORIZON_SEARCH, 'option') ?? false;
    }

    public static function canSearchEngineBeUsed(): bool
    {
        $config = self::getSearchEngineConfig();

        return $config && !empty($config[SearchEngineOptionsAdmin::FIELD_SEARCH_RESULTS_PAGE]);
    }
}
