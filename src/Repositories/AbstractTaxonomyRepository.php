<?php

declare(strict_types=1);

namespace Adeliom\HorizonTools\Repositories;

use Adeliom\HorizonTools\Database\QueryBuilder;
use Adeliom\HorizonTools\Database\TaxQuery;

abstract class AbstractTaxonomyRepository
{
    abstract static function getBaseQueryBuilder(?int $perPage = null, int $page = 1, bool $hideEmpty = true): QueryBuilder;

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

    private static function handleAs(QueryBuilder $qb, ?string $as = null): QueryBuilder
    {
        if (!empty($as)) {
            if (!class_exists($as)) {
                throw new \Exception(sprintf('Class %s does not exist', $as));
            }

            $qb->as($as);
        }

        return $qb;
    }

    /**
     * @return \WP_Term[]|object[]
     */
    public static function getAll(bool $hideEmpty = true, ?string $as = null): array
    {
        $qb = static::getBaseQueryBuilder(hideEmpty: $hideEmpty);

        self::handleAs(qb: $qb, as: $as);

        return $qb->get();
    }

    public static function getOneBySlug(string $slug, bool $hideEmpty = true): ?\WP_Term
    {
        $qb = static::getBaseQueryBuilder(hideEmpty: $hideEmpty)->whereSlug(slug: $slug);

        return $qb->getOneOrNull();
    }

    public static function getOneById(int $id, bool $hideEmpty = true): ?\WP_Term
    {
        $qb = static::getBaseQueryBuilder(hideEmpty: $hideEmpty)->whereIdIn([$id]);

        return $qb->getOneOrNull();
    }

    /**
     * @return \WP_Term[]|object[]
     */
    public static function getByIDs(array $ids, bool $hideEmpty = true, ?string $as = null): array
    {
        $qb = static::getBaseQueryBuilder(hideEmpty: $hideEmpty)->whereIdIn(ids: $ids);

        self::handleAs(qb: $qb, as: $as);

        return $qb->get();
    }

    public static function getPaginated(?int $perPage = null, int $page = 1, bool $hideEmpty = true, ?string $as = null): array
    {
        if (null === $perPage) {
            $perPage = static::$perPage;
        }

        $qb = static::getBaseQueryBuilder(perPage: $perPage, page: $page, hideEmpty: $hideEmpty);

        self::handleAs(qb: $qb, as: $as);

        return $qb->getPaginatedData();
    }
}
