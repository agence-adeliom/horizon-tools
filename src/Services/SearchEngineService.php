<?php

declare(strict_types=1);

namespace Adeliom\HorizonTools\Services;

use Adeliom\HorizonTools\Admin\SearchEngineOptionsAdmin;
use Adeliom\HorizonTools\Database\MetaQuery;
use Adeliom\HorizonTools\Database\QueryBuilder;
use Adeliom\HorizonTools\ViewModels\Post\BasePostViewModel;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;

class SearchEngineService
{
    public const HORIZON_SEARCH_ENGINE_CONFIG_CACHE_KEY = 'search_engine_config';

    public static function isSearchEngineEnabled(): bool
    {
        return Config::get('search-engine.enabled', false);
    }

    public static function getSearchEngineConfigPageUrl(): false|string
    {
        if (!self::isSearchEngineEnabled()) {
            return false;
        }

        return admin_url(sprintf('?page=%s', SearchEngineOptionsAdmin::$slug));
    }

    public static function getSearchEngineConfig(): false|array
    {
        return Cache::remember('', 60 * 60, function () {
            if (!self::isSearchEngineEnabled()) {
                return false;
            }

            return get_field(SearchEngineOptionsAdmin::FIELD_HORIZON_SEARCH, 'option') ?? false;
        });
    }

    public static function canSearchEngineBeUsed(): bool
    {
        $config = self::getSearchEngineConfig();

        return $config && !empty($config[SearchEngineOptionsAdmin::FIELD_SEARCH_RESULTS_PAGE]);
    }

    public static function getSearchEngineResultsPage(): ?\WP_Post
    {
        $page = null;

        if ($config = self::getSearchEngineConfig()) {
            if (!empty($config[SearchEngineOptionsAdmin::FIELD_SEARCH_RESULTS_PAGE])) {
                if ($config[SearchEngineOptionsAdmin::FIELD_SEARCH_RESULTS_PAGE] instanceof \WP_Post) {
                    $page = $config[SearchEngineOptionsAdmin::FIELD_SEARCH_RESULTS_PAGE];
                }
            }
        }

        return $page;
    }

    public static function searchPostTypes(
        string|array $postTypes,
        string $query,
        bool $separateResultsByType = false,
        int|array $page = 1,
        int $perPage = -1
    ) {
        $results = [];
        $postTypeNames = [];
        $postTypeTitles = [];

        if (is_string($postTypes)) {
            $postTypes = [$postTypes];
        }

        foreach ($postTypes as $typeToFetch) {
            $postTypeClass = ClassService::getPostTypeClassBySlug($typeToFetch);
            $postTypeInstance = null;
            $postTypeConfig = null;

            $searchableFields = [];

            switch ($typeToFetch) {
                case 'page':
                    $postTypeNames[$typeToFetch] = __('Pages');
                    $postTypeTitles[$typeToFetch] = Config::get('pages.search.title', __('Toutes les pages'));
                    break;
                case 'post':
                    $postTypeNames[$typeToFetch] = __('Articles');
                    $postTypeTitles[$typeToFetch] = Config::get('posts.search.title', __('Tous les articles'));
                    break;
                default:
                    $postTypeInstance = new $postTypeClass();

                    if (method_exists($postTypeInstance, 'getConfig')) {
                        $postTypeConfig = $postTypeInstance->getConfig();
                    }

                    if (method_exists($postTypeInstance, 'getSearchResultsTitle')) {
                        $postTypeTitles[$typeToFetch] = $postTypeInstance->getSearchResultsTitle();
                    }

                    if (!empty($postTypeConfig) && !empty($postTypeConfig['args']['labels']['name'])) {
                        $postTypeNames[$typeToFetch] = $postTypeConfig['args']['labels']['name'];
                    }
                    break;
            }

            if ($postTypeClass && method_exists($postTypeClass, 'getSearchableFields')) {
                $searchableFields = $postTypeClass::getSearchableFields() ?? [];
            }

            $realPage = $page;

            if ($separateResultsByType) {
                $realPage = 1;

                if (!empty($page[$typeToFetch])) {
                    $realPage = $page[$typeToFetch];
                }
            }

            $resultsPerType[$typeToFetch] = self::fetchDataByType(
                query: $query,
                postTypeSlug: $typeToFetch,
                postTypeClass: $postTypeClass,
                searchableFields: $searchableFields,
                all: true,
                page: $realPage,
                perPage: $perPage
            );
        }

        if ($separateResultsByType) {
            foreach ($resultsPerType as $postTypeSlug => $posts) {
                $realPage = 1;

                if (!empty($page[$postTypeSlug])) {
                    $realPage = $page[$postTypeSlug];
                }

                $allIDs = array_column($posts, 'ID');

                if (!empty($allIDs)) {
                    $qb = new QueryBuilder();
                    $qb->postType($postTypeSlug)
                        ->page($realPage)
                        ->perPage($perPage)
                        ->whereIdIn($allIDs)
                        ->as(BasePostViewModel::class);

                    $results[$postTypeSlug] = $qb->getPaginatedData(
                        callback: function (BasePostViewModel $result) {
                            return $result->toStdClass();
                        }
                    );

                    $results[$postTypeSlug]['title'] = $postTypeTitles[$postTypeSlug] ? $postTypeTitles[$postTypeSlug] : __('Résultats');
                }
            }
        } else {
            $qb = new QueryBuilder();

            $IDs = [];
            foreach ($resultsPerType as $posts) {
                $IDs[] = array_column($posts, 'ID');
            }

            $allIDs = array_merge(...$IDs);

            if (!empty($allIDs)) {
                $qb->postType($postTypes)
                    ->whereIdIn($allIDs)
                    ->as(BasePostViewModel::class)
                    ->page($page)
                    ->perPage($perPage);

                $results = $qb->getPaginatedData(
                    callback: function (BasePostViewModel $result) {
                        return $result->toStdClass();
                    }
                );
            }
        }

        return $results;
    }

    private static function fetchDataByType(
        string $query,
        string $postTypeSlug,
        ?string $postTypeClass,
        array $searchableFields = [],
        bool $all = false,
        int|string $page = 1,
        int|string $perPage = -1
    ): array {
        $relationWithOtherWheres = 'AND';

        if ($searchableFields) {
            $relationWithOtherWheres = 'OR';
        }

        if (is_string($page)) {
            if (is_numeric($page)) {
                $page = intval($page);
            } else {
                throw new \Exception('Page must be an integer or numeric string.');
            }
        }

        if (is_string($perPage)) {
            if (is_numeric($perPage)) {
                $perPage = intval($perPage);
            } else {
                throw new \Exception('Per page must be an integer or numeric string.');
            }
        }

        $qb = self::getBaseSearchQueryBuilder(query: $query, page: 1, perPage: -1, relationWithOtherWheres: $relationWithOtherWheres)
            ->postType($postTypeSlug)
            ->as(BasePostViewModel::class);

        if (!$all) {
            $qb->page($page)->perPage($perPage);
        }

        if ($searchableFields) {
            $orQuery = new MetaQuery();
            $orQuery->setRelation('OR');

            foreach ($searchableFields as $searchableField) {
                $subQuery = new MetaQuery();
                $subQuery->add($searchableField, $query, 'LIKE');

                $orQuery->add($subQuery);
            }

            $qb->addMetaQuery($orQuery);
        }

        return $qb->get(
            callback: function (BasePostViewModel $result) {
                return $result->toStdClass();
            }
        );
    }

    private static function getBaseSearchQueryBuilder(
        string $query,
        int $page,
        int $perPage,
        string $relationWithOtherWheres = 'AND'
    ): QueryBuilder {
        $qb = new QueryBuilder();
        $qb->page($page)->perPage($perPage)->search(search: $query, relationWithOtherWheres: $relationWithOtherWheres);

        if ($searchPage = SearchEngineService::getSearchEngineResultsPage()) {
            $qb->whereIdNotIn($searchPage->ID);
        }

        return $qb;
    }
}
