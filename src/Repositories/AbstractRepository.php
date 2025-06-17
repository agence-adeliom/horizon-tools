<?php

declare(strict_types=1);

namespace Adeliom\HorizonTools\Repositories;

use Adeliom\HorizonTools\Database\QueryBuilder;
use Adeliom\HorizonTools\Database\TaxQuery;

abstract class AbstractRepository
{
    abstract static function getBaseQueryBuilder(?int $perPage = null, int $page = 1): QueryBuilder;

    public static function handlePagination(QueryBuilder $qb, ?int $perPage = null, int $page = 1): QueryBuilder
    {
        $qb->page($page);

        if (null !== $perPage) {
            $qb->perPage($perPage);
        } else {
            $qb->perPage(-1);
        }

        return $qb;
    }

    /**
     * @return \WP_Post[]
     */
    public static function getAll(?int $perPage = null, int $page = 1): array
    {
        $qb = static::getBaseQueryBuilder()->setPage($page);

        if (null === $perPage) {
            $qb->perPage(-1);
        } else {
            $qb->perPage($perPage);
        }

        return $qb->get();
    }

    public static function getOneBySlug(string $slug, ?string $status = null): ?\WP_Post
    {
        $qb = static::getBaseQueryBuilder()->whereSlug(slug: $slug);

        if (null !== $status) {
            $qb->status($status);
        }

        return $qb->getOneOrNull();
    }

    public static function getOneById(int $id, ?string $status = null): ?\WP_Post
    {
        $qb = static::getBaseQueryBuilder()->whereIdIn([$id]);

        if (null !== $status) {
            $qb->status($status);
        }

        return $qb->getOneOrNull();
    }

    public static function getPaginated(?int $perPage = null, int $page = 1)
    {
        if (null === $perPage) {
            $perPage = static::$perPage;
        }

        return static::getBaseQueryBuilder(perPage: $perPage, page: $page)->getPaginatedData();
    }

    /**
     * @param \WP_Term|\WP_Term[] $term
     * @return array
     */
    public static function getByTerms(\WP_Term|array $terms, string $relation = 'AND', ?int $perPage = null, ?int $page = 1): array
    {
        $qb = static::getBaseQueryBuilder(perPage: $perPage, page: $page);

        if (!is_array($terms)) {
            $terms = [$terms];
        }

        $taxQueries = new TaxQuery();
        $taxQueries->setRelation($relation);

        foreach ($terms as $term) {
            $taxQuery = new TaxQuery();
            $taxQuery->add($term->taxonomy, $term->slug);

            $taxQueries->add($taxQuery);
        }

        $qb->addTaxQuery($taxQueries);

        if (null !== $perPage) {
            return $qb->getPaginatedData();
        }

        return $qb->get();
    }

    public static function getByParent(int|\WP_Post $parent, ?int $perPage = null, int $page = 1): array
    {
        $qb = static::getBaseQueryBuilder(perPage: $perPage, page: $page)->whereParentIn($parent);

        return $qb->get();
    }
}
