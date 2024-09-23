<?php

declare(strict_types=1);

namespace Adeliom\HorizonTools\Database;

class TaxQuery
{
    private string $relation = 'AND';
    private array $query = [];

    public function __construct()
    {
    }

    public function add(
        string|self $taxonomyOrTaxQuery,
        null|string|int|array $terms = null,
        string $field = 'slug',
        string $operator = 'IN',
        bool $includeChildren = false
    ) {
        $finalOperator = 'IN';
        $finalField = 'slug';

        if (in_array($operator, ['IN', 'NOT IN', 'AND', 'EXISTS', 'NOT EXISTS'])) {
            $finalOperator = $operator;
        }

        if (in_array($field, ['slug', 'term_id', 'name', 'term_taxonomy_id'])) {
            $finalField = $field;
        }

        if (is_string($taxonomyOrTaxQuery)) {
            $data = [
                'taxonomy' => $taxonomyOrTaxQuery,
                'field' => $finalField,
                'terms' => $terms,
                'operator' => $finalOperator,
                'include_children' => $includeChildren,
            ];

            $this->query[] = $data;
        } else {
            $this->query[] = $taxonomyOrTaxQuery;
        }

        return $this;
    }

    public function getQuery(): array
    {
        return $this->query;
    }

    public function setRelation(string $relation): self
    {
        if (in_array($relation, ['AND', 'OR'])) {
            $this->relation = $relation;
        }

        return $this;
    }

    public function getRelation(): string
    {
        return $this->relation;
    }

    public function generateTaxQueryArray(): array
    {
        $elements = array_map(function ($item) {
            if ($item instanceof self) {
                return $item->generateTaxQueryArray();
            } else {
                return $item;
            }
        }, $this->query);

        return [
            'relation' => $this->relation,
            ...$elements,
        ];
    }
}
