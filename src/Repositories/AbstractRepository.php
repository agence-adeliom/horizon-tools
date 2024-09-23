<?php

declare(strict_types=1);

namespace Adeliom\HorizonTools\Repositories;

use Adeliom\HorizonTools\Database\QueryBuilder;

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
}
