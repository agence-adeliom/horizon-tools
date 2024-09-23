<?php

declare(strict_types=1);

namespace Adeliom\HorizonTools\Repositories;

use Adeliom\HorizonTools\Database\QueryBuilder;
use Adeliom\HorizonTools\Database\TaxQuery;

abstract class AbstractTaxonomyRepository
{
    abstract static function getBaseQueryBuilder(): QueryBuilder;

    /**
     * @return \WP_Term[]
     */
    public static function getAll(bool $hideEmpty = true): array
    {
        return static::getBaseQueryBuilder(hideEmpty: $hideEmpty)->get();
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
}
