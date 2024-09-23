<?php

declare(strict_types=1);

namespace Adeliom\HorizonTools\Repositories;

use Adeliom\HorizonTools\Database\QueryBuilder;
use Adeliom\HorizonTools\Database\TaxQuery;

abstract class AbstractRepository
{
    abstract static function getBaseQueryBuilder(): QueryBuilder;

    /**
     * @return \WP_Post[]
     */
    public static function getAll(): array
    {
        return static::getBaseQueryBuilder()->get();
    }

    public static function getOneBySlug(string $slug, ?string $status = null): ?\WP_Post
    {
        $qb = static::getBaseQueryBuilder()->whereSlug(slug: $slug);

        if (null !== $status) {
            $qb->setStatus($status);
        }

        return $qb->getOneOrNull();
    }

    public static function getOneById(int $id, ?string $status = null): ?\WP_Post
    {
        $qb = static::getBaseQueryBuilder()->whereIdIn([$id]);

        if (null !== $status) {
            $qb->setStatus($status);
        }

        return $qb->getOneOrNull();
    }

    /**
     * @param \WP_Term|\WP_Term[] $term
     * @return array
     */
    public static function getByTerms(\WP_Term|array $terms, string $relation = 'AND', ?int $perPage = null, ?int $page = 1): array
    {
        $qb = static::getBaseQueryBuilder();

        if (null !== $perPage) {
            $qb->setPerPage($perPage);
            $qb->setPage($page);
        }

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
}
