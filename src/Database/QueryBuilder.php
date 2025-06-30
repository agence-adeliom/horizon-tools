<?php

declare(strict_types=1);

namespace Adeliom\HorizonTools\Database;

use Illuminate\Support\Facades\Cache;

class QueryBuilder
{
    private const ORDER_BY_META_KEY = 'meta_value';
    private const ORDER_BY_META_KEY_NUM = 'meta_value_num';

    public const SEARCH_COLUMN_TITLE = 'post_title';
    public const SEARCH_COLUMN_CONTENT = 'post_content';
    public const SEARCH_COLUMN_EXCERPT = 'post_excerpt';
    public const SEARCH_COLUMN_NAME = 'post_name';

    public const TAX_PREFIX = 'tax_';

    public const CACHE_DEFAULT_DURATION = 3600;

    public const DEFAULT_POST_SEARCH_COLUMNS = [self::SEARCH_COLUMN_TITLE, self::SEARCH_COLUMN_CONTENT, self::SEARCH_COLUMN_EXCERPT];

    private bool $isPostType = true;
    private bool $isTaxonomy = false;
    private array $postTypes = [];
    private array $taxonomies = [];
    private array $idIn = [];
    private array $idNotIn = [];
    private array $metaQueries = [];
    private array $taxQueries = [];
    private ?string $asClass = null;
    private ?int $perPage = null;
    private int $page = 1;
    private ?int $offset = null;
    private ?int $forcedOffset = null;
    private ?string $slug = null;
    private ?string $fields = null;
    private ?array $parentIdsIn = [];
    private string $orderBy = 'date';
    private string $order = 'DESC';
    private ?string $orderMetaKey = null;
    private ?string $status = 'publish';
    private ?string $search = null;
    private array $searchColumns = [];
    private bool $hideEmpty = false;
    private ?\WP_Query $WP_Query = null;
    private ?\WP_Term_Query $WP_Term_Query = null;

    private bool $withCache = false;
    private ?int $cacheDuration = null;
    private ?string $queryHash = null;

    private function triggerChange(): void
    {
        $this->WP_Query = null;
        $this->WP_Term_Query = null;
        $this->queryHash = null;
    }

    public function postType(string|array $postType): self
    {
        $this->triggerChange();
        $this->isTaxonomy = false;
        $this->isPostType = true;

        if (is_string($postType)) {
            $postType = [$postType];
        }

        foreach ($postType as $item) {
            if (!in_array($item, $this->postTypes)) {
                $this->postTypes[] = $item;
            }
        }

        return $this;
    }

    public function taxonomy(string|array $taxonomy): self
    {
        $this->triggerChange();
        $this->isPostType = false;
        $this->isTaxonomy = true;

        if (is_string($taxonomy)) {
            $taxonomy = [$taxonomy];
        }

        foreach ($taxonomy as $item) {
            if (!in_array($item, $this->taxonomies)) {
                $this->taxonomies[] = $item;
            }
        }

        return $this;
    }

    public function whereParentIn(int|\WP_Post|array $ids): self
    {
        $this->triggerChange();

        if (is_int($ids)) {
            $ids = [$ids];
        }

        if ($ids instanceof \WP_Post) {
            $ids = [$ids->ID];
        }

        foreach ($ids as $item) {
            if (!in_array($item, $this->parentIdsIn) && is_int($item)) {
                $this->parentIdsIn[] = $item;
            }
        }

        return $this;
    }

    public function whereIdIn(int|array $ids): self
    {
        $this->triggerChange();

        if (is_int($ids)) {
            $ids = [$ids];
        }

        foreach ($ids as $item) {
            if (!in_array($item, $this->idIn)) {
                $this->idIn[] = $item;
            }
        }

        return $this;
    }

    public function whereSlug(?string $slug = null): self
    {
        $this->triggerChange();

        $this->slug = $slug;

        return $this;
    }

    public function fields(?string $fields): self
    {
        if (null !== $fields) {
            switch (true) {
                case $this->isTaxonomy:
                    $allowedForTaxonomy = [
                        'all',
                        'all_with_object_id',
                        'ids',
                        'tt_ids',
                        'names',
                        'slugs',
                        'count',
                        'id=>parent',
                        'id=>name',
                        'id=>slug',
                    ];

                    if (!in_array($fields, $allowedForTaxonomy)) {
                        throw new \Exception(
                            sprintf('Invalid fields for taxonomy, it should be one of %s', implode(', ', $allowedForTaxonomy))
                        );
                    }

                    $this->fields = $fields;
                    break;
                case $this->isPostType:
                default:
                    $allowedForPostType = ['', 'ids', 'id=>parent'];

                    if (!in_array($fields, $allowedForPostType)) {
                        throw new \Exception(
                            sprintf('Invalid fields for post type, it should be one of %s', implode(', ', $allowedForPostType))
                        );
                    }

                    $this->fields = $fields;
                    break;
            }
        } else {
            $this->fields = null;
        }

        return $this;
    }

    public function removeIdIn(int|array $ids): self
    {
        $this->triggerChange();

        if (is_int($ids)) {
            $ids = [$ids];
        }

        foreach ($ids as $item) {
            if (in_array($item, $this->idIn)) {
                $this->idIn = array_diff($this->idIn, [$item]);
            }
        }

        return $this;
    }

    public function whereIdNotIn(int|array $ids): self
    {
        $this->triggerChange();

        if (is_int($ids)) {
            $ids = [$ids];
        }

        foreach ($ids as $item) {
            if (!in_array($item, $this->idNotIn)) {
                $this->idNotIn[] = $item;
            }
        }

        return $this;
    }

    public function removeIdNotIn(int|array $ids): self
    {
        $this->triggerChange();

        if (is_int($ids)) {
            $ids = [$ids];
        }

        foreach ($ids as $item) {
            if (in_array($item, $this->idNotIn)) {
                $this->idNotIn = array_diff($this->idNotIn, [$item]);
            }
        }

        return $this;
    }

    public function addMetaQuery(MetaQuery $metaQuery): self
    {
        $this->triggerChange();

        if ($metaQuery->getQuery()) {
            $this->metaQueries[] = $metaQuery;
        }

        return $this;
    }

    public function addTaxQuery(TaxQuery $taxQuery): self
    {
        $this->triggerChange();

        if ([] !== $taxQuery->getQuery()) {
            $this->taxQueries[] = $taxQuery;
        }

        return $this;
    }

    /**
     * Defines the number of first elements to ignore
     * @param int $offest
     * @return $this
     */
    public function offset(int $offest): self
    {
        $this->triggerChange();

        $this->offset = $offest;

        return $this;
    }

    public function forcedOffset(int $forcedOffset): self
    {
        $this->triggerChange();

        $this->forcedOffset = $forcedOffset;

        return $this;
    }

    public function page(int $page): self
    {
        $this->triggerChange();

        $this->page = $page;

        return $this;
    }

    public function getPage(): int
    {
        return $this->page;
    }

    /**
     * @deprecated
     */
    public function setPage(int $page): self
    {
        return $this->page(page: $page);
    }

    public function perPage(?int $perPage): self
    {
        $this->triggerChange();

        $this->perPage = $perPage;

        return $this;
    }

    public function getPerPage(): ?int
    {
        return $this->perPage;
    }

    /**
     * @deprecated
     */
    public function setPerPage(?int $perPage): self
    {
        return $this->perPage(perPage: $perPage);
    }

    public function status(string $status): self
    {
        $this->triggerChange();

        if (in_array($status, ['any', 'publish', 'pending', 'draft', 'future', 'auto-draft', 'private', 'inherit', 'trash'])) {
            $this->status = $status;
        }

        return $this;
    }

    /**
     * @deprecated
     */
    public function setStatus(string $status): self
    {
        return $this->status(status: $status);
    }

    public function search(string $search, array $columns = self::DEFAULT_POST_SEARCH_COLUMNS): self
    {
        $this->triggerChange();

        $this->search = $search;
        $this->searchColumns = $columns;

        return $this;
    }

    public function fetchEmptyTaxonomies(bool $fetchEmpty = true): self
    {
        $this->triggerChange();
        $this->hideEmpty = !$fetchEmpty;

        return $this;
    }

    public function orderBy(string $order = 'DESC', string $orderBy = 'date', bool $isMeta = false, bool $isMetaNumeric = false): self
    {
        $this->triggerChange();

        $this->order = $order;

        if (!$isMeta) {
            $this->orderBy = $orderBy;
        } else {
            if ($isMetaNumeric) {
                $this->orderBy = self::ORDER_BY_META_KEY_NUM;
            } else {
                $this->orderBy = self::ORDER_BY_META_KEY;
            }

            $this->orderMetaKey = $orderBy;
        }

        return $this;
    }

    /**
     * Allows to order a posts query by the name of the terms of a taxonomy
     */
    public function orderByTaxonomy(string $taxonomy, string $order = 'DESC'): self
    {
        $this->orderBy($order, sprintf('%s%s', self::TAX_PREFIX, $taxonomy));

        return $this;
    }

    private function generateCacheKey(): ?string
    {
        $args = null;
        $prefix = null;

        switch (true) {
            case $this->isPostType:
                $args = $this->getWpQueryArgs();
                $prefix = 'post';
                break;
            case $this->isTaxonomy:
                $args = $this->getWpTaxQueryArgs();
                $prefix = 'tax';
                break;
            default:
                break;
        }

        if (null !== $args) {
            $this->queryHash = sprintf('%s_%s', $prefix, hash('sha256', serialize($args)));
        } else {
            $this->queryHash = null;
        }

        return $this->queryHash;
    }

    private function getWpQueryArgs(): array
    {
        $args = [];

        if ($this->status) {
            $args['post_status'] = $this->status;
        }

        if ($this->slug) {
            $args['name'] = $this->slug;
        }

        if (null !== $this->fields) {
            $args['fields'] = $this->fields;
        }

        if ($this->postTypes) {
            $args['post_type'] = $this->postTypes;
        }

        if ($this->idIn) {
            $args['post__in'] = $this->idIn;
        }

        if ($this->idNotIn) {
            $args['post__not_in'] = $this->idNotIn;
        }

        if ($this->parentIdsIn) {
            $args['post_parent__in'] = $this->parentIdsIn;
        }

        if ($this->search) {
            $args['s'] = $this->search;
        }

        if ($this->searchColumns) {
            $args['search_columns'] = $this->searchColumns;
        }

        if ($this->page) {
            $args['page'] = $this->page;

            $forcedOffset = null;

            if (null !== $this->forcedOffset) {
                $forcedOffset = $this->forcedOffset;
            }

            if ($this->perPage) {
                $args['posts_per_page'] = $this->perPage;

                $offset = $forcedOffset;

                if (null === $offset) {
                    $offset = ($this->page - 1) * $this->perPage;

                    if (null !== $this->offset) {
                        $offset += $this->offset;
                    }
                }

                $args['offset'] = $offset;
            } else {
                $args['offset'] = $forcedOffset ?? 0;
            }
        }

        if ([] !== $this->metaQueries) {
            foreach ($this->metaQueries as $metaQuery) {
                if ($metaQuery instanceof MetaQuery) {
                    $args['meta_query'][] = $metaQuery->generateMetaQueryArray();
                }
            }
        }

        if ([] !== $this->taxQueries) {
            foreach ($this->taxQueries as $taxQuery) {
                if ($taxQuery instanceof TaxQuery) {
                    $args['tax_query'][] = $taxQuery->generateTaxQueryArray();
                }
            }
        }

        $args['orderby'] = $this->orderBy;
        $args['order'] = $this->order;

        if (in_array($this->orderBy, [self::ORDER_BY_META_KEY, self::ORDER_BY_META_KEY_NUM])) {
            $args['meta_key'] = $this->orderMetaKey;
        }

        return $args;
    }

    private function getWpQuery(): \WP_Query
    {
        if (null === $this->WP_Query) {
            $args = $this->getWpQueryArgs();

            $this->WP_Query = new \WP_Query($args);
        }

        return $this->WP_Query;
    }

    private function getWpTaxQueryArgs(): array
    {
        $args = [];

        if ($this->taxonomies) {
            $args['taxonomy'] = $this->taxonomies;
        }

        if ($this->page) {
            $args['offset'] = ($this->page - 1) * $this->perPage;
        }

        if ($this->perPage) {
            if ($this->perPage === -1) {
                $this->perPage = 0;
            }

            $args['number'] = $this->perPage;
        }

        if ($this->idNotIn) {
            $args['exclude'] = $this->idNotIn;
        }

        if ($this->idIn) {
            $args['include'] = $this->idIn;
        }

        if ($this->slug) {
            $args['slug'] = $this->slug;
        }

        if (null !== $this->fields) {
            $args['fields'] = $this->fields;
        }

        $args['hide_empty'] = $this->hideEmpty;

        $args['orderby'] = $this->orderBy;
        $args['order'] = $this->order;

        if ([] !== $this->metaQueries) {
            foreach ($this->metaQueries as $metaQuery) {
                if ($metaQuery instanceof MetaQuery) {
                    $args['meta_query'][] = $metaQuery->generateMetaQueryArray();
                }
            }
        }

        return $args;
    }

    private function getWpTaxQuery(): \WP_Term_Query
    {
        if (null === $this->WP_Term_Query) {
            $args = $this->getWpTaxQueryArgs();

            $this->WP_Term_Query = new \WP_Term_Query($args);
        }

        return $this->WP_Term_Query;
    }

    public function getQuery(): \WP_Query|\WP_Term_Query
    {
        if ($this->isPostType) {
            return $this->getWpQuery();
        } elseif ($this->isTaxonomy) {
            return $this->getWpTaxQuery();
        }
    }

    public function as(?string $class = null): self
    {
        $this->asClass = $class;

        return $this;
    }

    public function useCache(int $duration = self::CACHE_DEFAULT_DURATION): self
    {
        $useCache = true;

        if (defined('DISABLE_QUERY_BUILDER_CACHE')) {
            $useCache = !DISABLE_QUERY_BUILDER_CACHE;
        }

        if ($useCache) {
            if (!$this->withCache) {
                $this->withCache = true;
            }

            $this->cacheDuration = $duration;
        }

        return $this;
    }

    public function disableCache(): self
    {
        if ($this->withCache) {
            $this->withCache = false;
            $this->cacheDuration = null;
        }

        return $this;
    }

    /**
     * @return \WP_Post[]|\WP_Term[]
     */
    public function get(?callable $callback = null): array
    {
        $results = null;
        $cacheKey = null;

        if ($this->withCache) {
            $cacheKey = $this->generateCacheKey();

            if ($cacheKey) {
                if ($results = Cache::get($cacheKey)) {
                    return $results;
                }
            }
        }

        if ($this->isPostType) {
            $results = $this->getQuery()->posts;
        } elseif ($this->isTaxonomy) {
            $results = $this->getQuery()->terms;
        }

        if (!empty($this->asClass) && class_exists($this->asClass)) {
            $results = array_map(function ($postOrTerm) use ($callback) {
                return null !== $callback ? $callback(new $this->asClass($postOrTerm)) : new $this->asClass($postOrTerm);
            }, $results);
        } else {
            if (null !== $callback) {
                $results = array_map($callback, $results);
            }
        }

        $toReturn = null !== $results ? $results : [];

        if ($this->withCache && $cacheKey) {
            Cache::put($cacheKey, $toReturn, $this->cacheDuration);
        }

        return $toReturn;
    }

    public function getOneOrNull(): mixed
    {
        $query = clone $this->getQuery();

        $results = null;

        if ($this->isPostType) {
            $query->query['posts_per_page'] = 1;
            $query->set('posts_per_page', 1);

            $results = $query->get_posts();
        } elseif ($this->isTaxonomy) {
            $query->query['number'] = 1;

            $results = $query->get_terms();
        }

        if ($results) {
            if (!empty($this->asClass)) {
                return new $this->asClass($results[0]);
            }

            return $results[0];
        }

        return null;
    }

    public function getCount(): ?int
    {
        $count = $this->getQuery()->found_posts;

        if (null !== $this->offset) {
            $count -= $this->offset;
        }

        return $count;
    }

    public function getPagesCount(): ?int
    {
        if ($this->isPostType) {
            $count = $this->getCount();

            if (null !== $this->perPage) {
                return intval(ceil($count / $this->perPage));
            }
        }

        return null;
    }

    public function getPaginatedData(?callable $callback = null): array
    {
        $items = $this->get(callback: $callback);
        $total = 0;
        $pages = 0;

        if ($this->isPostType) {
            $total = $this->getCount();
            $pages = $this->getPagesCount();
        } elseif ($this->isTaxonomy) {
            $clone = clone $this;
            $clone->perPage(null);
            $total = $clone->get();

            $total = count($total);

            if (null !== $this->offset) {
                $total -= $this->offset;
            }

            $pages = intval(ceil($total / $this->perPage));
        }

        return [
            'items' => $items,
            'perPage' => $this->perPage,
            'pages' => $pages,
            'total' => $total,
            'current' => $this->page,
        ];
    }
}
