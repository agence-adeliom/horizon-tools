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

    public static $excludedIDs = [];

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
        if (!self::isSearchEngineEnabled()) {
            return false;
        }

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

    public static function getSearchEngineResultsUrl(): ?string
    {
        if ($page = self::getSearchEngineResultsPage()) {
            $url = get_permalink($page);
        } else {
            $url = home_url('/');
        }

        return $url;
    }

    public static function getSearchEngineCurrentSearchQuery(): ?string
    {
        $query = null;

        if (!empty($_GET[self::getSearchEngineGETParameter()])) {
            $query = sanitize_text_field($_GET[self::getSearchEngineGETParameter()]);
        }

        return $query;
    }

    public static function getSearchEngineGETParameter(): ?string
    {
        return Cache::remember('search_engine_get_parameter', 60 * 60, function () {
            $param = null;

            if ($config = self::getSearchEngineConfig()) {
                if (!empty($config[SearchEngineOptionsAdmin::FIELD_SEARCH_GET_PARAMETER])) {
                    $param = $config[SearchEngineOptionsAdmin::FIELD_SEARCH_GET_PARAMETER];
                }
            }

            return $param;
        });
    }

    public static function getSearchEnginePageGETParameter(): ?string
    {
        return Cache::remember('search_engine_page_get_parameter', 60 * 60, function () {
            $param = null;

            if ($config = self::getSearchEngineConfig()) {
                if (!empty($config[SearchEngineOptionsAdmin::FIELD_PAGE_GET_PARAMETER])) {
                    $param = $config[SearchEngineOptionsAdmin::FIELD_PAGE_GET_PARAMETER];
                }
            }

            return $param;
        });
    }

    public static function getExcludedIDs(): array
    {
        return Cache::remember('search_engine_excluded_IDs', 60 * 60, function () {
            $excludedIDs = [];

            if ($config = self::getSearchEngineConfig()) {
                if (
                    !empty($config[SearchEngineOptionsAdmin::FIELD_EXCLUDED_POSTS]) &&
                    is_array($config[SearchEngineOptionsAdmin::FIELD_EXCLUDED_POSTS])
                ) {
                    $excludedIDs = $config[SearchEngineOptionsAdmin::FIELD_EXCLUDED_POSTS];
                }
            }

            return $excludedIDs;
        });
    }

    public static function getPerPage(): ?int
    {
        return Cache::remember('search_engine_per_page', 60 * 60, function () {
            $perPage = null;

            if ($config = self::getSearchEngineConfig()) {
                if (
                    !empty($config[SearchEngineOptionsAdmin::FIELD_PER_PAGE]) &&
                    is_numeric($config[SearchEngineOptionsAdmin::FIELD_PER_PAGE])
                ) {
                    $perPage = intval($config[SearchEngineOptionsAdmin::FIELD_PER_PAGE]);
                }
            }

            return $perPage;
        });
    }

    public static function getSeparateByTypes(): bool
    {
        return Cache::remember('search_engine_separate_by_type', 60 * 60, function () {
            $separateByTypes = false;

            if ($config = self::getSearchEngineConfig()) {
                if (
                    !empty($config[SearchEngineOptionsAdmin::FIELD_SEPARATE_BY_TYPES]) &&
                    is_bool($config[SearchEngineOptionsAdmin::FIELD_SEPARATE_BY_TYPES])
                ) {
                    $separateByTypes = $config[SearchEngineOptionsAdmin::FIELD_SEPARATE_BY_TYPES];
                }
            }

            return $separateByTypes;
        });
    }

    public static function getPostTypes(): ?array
    {
        return Cache::remember('search_engine_post_types', 60 * 60, function () {
            $postTypes = null;

            if ($config = self::getSearchEngineConfig()) {
                if (
                    !empty($config[SearchEngineOptionsAdmin::FIELD_SEARCH_TYPES]) &&
                    is_array($config[SearchEngineOptionsAdmin::FIELD_SEARCH_TYPES])
                ) {
                    $postTypes = $config[SearchEngineOptionsAdmin::FIELD_SEARCH_TYPES];
                }
            }

            return $postTypes;
        });
    }

    public static function getAllowFilterByType(): bool
    {
        return Cache::remember('search_engine_filter_by_type', 60 * 60, function () {
            $allowFilterByType = false;

            if ($config = self::getSearchEngineConfig()) {
                if (
                    !empty($config[SearchEngineOptionsAdmin::FIELD_ALLOW_FILTER_BY_TYPE]) &&
                    is_bool($config[SearchEngineOptionsAdmin::FIELD_ALLOW_FILTER_BY_TYPE])
                ) {
                    $allowFilterByType = $config[SearchEngineOptionsAdmin::FIELD_ALLOW_FILTER_BY_TYPE];
                }
            }

            return $allowFilterByType;
        });
    }

    public static function getHeaderTitle(): ?string
    {
        return Cache::remember('search_engine_header_title', 60 * 60, function () {
            $headerTitle = null;

            if ($config = self::getSearchEngineConfig()) {
                if (
                    !empty($config[SearchEngineOptionsAdmin::FIELD_SEARCH_HEADER_TITLE]) &&
                    is_string($config[SearchEngineOptionsAdmin::FIELD_SEARCH_HEADER_TITLE])
                ) {
                    $headerTitle = $config[SearchEngineOptionsAdmin::FIELD_SEARCH_HEADER_TITLE];
                }
            }

            return $headerTitle;
        });
    }

    public static function getMetaTitle(): ?string
    {
        return Cache::remember('search_engine_meta_title', 60 * 60, function () {
            $metaTitle = null;

            if ($config = self::getSearchEngineConfig()) {
                if (
                    !empty($config[SearchEngineOptionsAdmin::FIELD_META_TITLE]) &&
                    is_string($config[SearchEngineOptionsAdmin::FIELD_META_TITLE])
                ) {
                    $metaTitle = $config[SearchEngineOptionsAdmin::FIELD_META_TITLE];
                }
            }

            return $metaTitle;
        });
    }

    public static function getDisplayBreadcrumbs(): bool
    {
        return Cache::remember('search_engine_display_breadcrumbs', 60 * 60, function () {
            $displayBreadcrumbs = false;

            if ($config = self::getSearchEngineConfig()) {
                if (
                    !empty($config[SearchEngineOptionsAdmin::FIELD_SEARCH_HEADER_HAS_BREADCRUMBS]) &&
                    is_bool($config[SearchEngineOptionsAdmin::FIELD_SEARCH_HEADER_HAS_BREADCRUMBS])
                ) {
                    $displayBreadcrumbs = $config[SearchEngineOptionsAdmin::FIELD_SEARCH_HEADER_HAS_BREADCRUMBS];
                }
            }

            return $displayBreadcrumbs;
        });
    }

    public static function getHeaderImage(): ?array
    {
        return Cache::remember('search_engine_header_image', 60 * 60, function () {
            $headerImage = null;

            if ($config = self::getSearchEngineConfig()) {
                if (
                    !empty($config[SearchEngineOptionsAdmin::FIELD_SEARCH_HEADER_IMAGE]) &&
                    is_array($config[SearchEngineOptionsAdmin::FIELD_SEARCH_HEADER_IMAGE])
                ) {
                    $headerImage = $config[SearchEngineOptionsAdmin::FIELD_SEARCH_HEADER_IMAGE];
                }
            }

            return $headerImage;
        });
    }

    public static function getAddPageToMetaTitle(): bool
    {
        return Cache::remember('search_engine_add_page_to_meta_title', 60 * 60, function () {
            $addPageToMetaTitle = false;

            if ($config = self::getSearchEngineConfig()) {
                if (
                    !empty($config[SearchEngineOptionsAdmin::FIELD_META_TITLE_ADD_PAGE]) &&
                    is_bool($config[SearchEngineOptionsAdmin::FIELD_META_TITLE_ADD_PAGE])
                ) {
                    $addPageToMetaTitle = $config[SearchEngineOptionsAdmin::FIELD_META_TITLE_ADD_PAGE];
                }
            }

            return $addPageToMetaTitle;
        });
    }

    public static function searchPostTypes(
        string|array $postTypes,
        string|array $onlyGetResultsFromPostTypes = [],
        string $query = '',
        bool $separateResultsByType = false,
        int|array $page = 1,
        int $perPage = -1,
        array &$foundPostTypes = [],
        ?array &$totalPerType = []
    ) {
        $results = [];
        $postTypeNames = [];
        $postTypeTitles = [];

        if (is_string($postTypes)) {
            $postTypes = [$postTypes];
        }

        if (is_string($onlyGetResultsFromPostTypes)) {
            $onlyGetResultsFromPostTypes = [$onlyGetResultsFromPostTypes];
        }

        if (empty($onlyGetResultsFromPostTypes)) {
            $onlyGetResultsFromPostTypes = $postTypes;
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

            $totalPerType[$typeToFetch] = 0;

            $resultsPerType[$typeToFetch] = self::fetchDataByType(
                query: $query,
                postTypeSlug: $typeToFetch,
                postTypeClass: $postTypeClass,
                searchableFields: $searchableFields,
                all: true,
                page: $realPage,
                perPage: $perPage,
                count: $totalPerType[$typeToFetch]
            );

            if (!empty($resultsPerType[$typeToFetch])) {
                $foundPostTypes[] = $typeToFetch;
            }
        }

        if ($separateResultsByType) {
            foreach ($onlyGetResultsFromPostTypes as $onlyGetResultsFromPostType) {
                if (!empty($resultsPerType[$onlyGetResultsFromPostType])) {
                    $postTypeSlug = $onlyGetResultsFromPostType;
                    $posts = $resultsPerType[$postTypeSlug];

                    $realPage = 1;

                    if (!empty($page[$postTypeSlug])) {
                        $realPage = $page[$postTypeSlug];
                    }

                    $allIDs = self::handleIDsBeforeSearch(array_column($posts, 'ID'));

                    if (!empty($allIDs)) {
                        $qb = new QueryBuilder();
                        $qb->postType($postTypeSlug)
                            ->page($realPage)
                            ->perPage($perPage)
                            ->whereIdIn($allIDs)
                            ->orderBy(order: 'DESC', orderBy: 'date')
                            ->as(BasePostViewModel::class);

                        $results[$postTypeSlug] = $qb->getPaginatedData(
                            callback: function (BasePostViewModel $result) {
                                return $result->toStdClass();
                            }
                        );

                        $results[$postTypeSlug]['title'] = $postTypeTitles[$postTypeSlug]
                            ? $postTypeTitles[$postTypeSlug]
                            : __('Résultats');
                    }
                }
            }
        } else {
            $qb = new QueryBuilder();

            $IDs = [];

            foreach ($onlyGetResultsFromPostTypes as $onlyGetResultsFromPostType) {
                if (!empty($resultsPerType[$onlyGetResultsFromPostType])) {
                    $IDs[] = array_column($resultsPerType[$onlyGetResultsFromPostType], 'ID');
                }
            }

            $allIDs = self::handleIDsBeforeSearch(array_merge(...$IDs));

            if (!empty($allIDs)) {
                $qb->postType($postTypes)
                    ->whereIdIn($allIDs)
                    ->as(BasePostViewModel::class)
                    ->orderBy(order: 'DESC', orderBy: 'date')
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

    private static function handleIDsBeforeSearch(array $IDs): array
    {
        if (empty(self::$excludedIDs)) {
            $excludedIDs = self::getExcludedIDs();
        }

        if (!empty(self::$excludedIDs) && is_array(self::$excludedIDs)) {
            $IDs = array_diff($IDs, $excludedIDs);
        }

        return $IDs;
    }

    private static function fetchDataByType(
        string $query,
        string $postTypeSlug,
        ?string $postTypeClass,
        array $searchableFields = [],
        bool $all = false,
        int|string $page = 1,
        int|string $perPage = -1,
        int &$count = 0
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

        $count = $qb->getCount();

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
