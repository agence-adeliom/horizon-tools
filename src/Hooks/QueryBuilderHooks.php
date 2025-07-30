<?php

declare(strict_types=1);

namespace Adeliom\HorizonTools\Hooks;

use Adeliom\HorizonTools\Database\QueryBuilder;

class QueryBuilderHooks extends AbstractHook
{
    public function init(): void
    {
        add_filter('posts_clauses', [$this, 'orderByTaxonomyTerms'], accepted_args: 2);
        add_filter('posts_search', [$this, 'handlePostSearchRelation'], accepted_args: 2);
        add_filter('posts_where', [$this, 'handlePostsWhere'], accepted_args: 2);
        add_filter('posts_clauses', [$this, 'handleMetaSql'], accepted_args: 4);
    }

    public function handlePostSearchRelation(string $search, \WP_Query $query): string
    {
        if ($query->get('search_relation_with_other_wheres') === 'OR') {
            $search = ' AND 1=1 '; // Reset search to avoid conflicts with other search clauses
        }

        return $search;
    }

    public function handlePostsWhere(string $where, \WP_Query $query): string
    {
        if ($query->get('search_relation_with_other_wheres') === 'OR') {
            $whereParts = explode('AND 1=1', $where);

            $where = $whereParts[0] . ' AND 1=1 ';

            // Remove 0 index from $whereParts
            array_splice($whereParts, 0, 1);

            $where .= implode(' ', $whereParts);
        }

        return $where;
    }

    public function handleMetaSql(array $clauses, \WP_Query $query): array
    {
        if ($query->get('search_relation_with_other_wheres') === 'OR') {
            $where = $clauses['where'];

            $search = $this->buildSearchQuery($query);

            // Extract the meta query from the where clause
            preg_match('/\(+\s*ad_postmeta\.meta_key\s*=\s*[^)]+meta_value\s+LIKE\s+[^)]+\)+/s', $where, $matches);
            $metaQuery = $matches[0] ?? null;

            if ($search && $metaQuery) {
                $where = str_replace($metaQuery, sprintf('(%s OR %s)', $search, $metaQuery), $where);

                $clauses['where'] = $where;
            }
        }

        return $clauses;
    }

    private function buildSearchQuery(\WP_Query $query, string $relation = 'OR'): string
    {
        global $wpdb;

        $columns = $query->get('search_columns');
        $searchTerm = $query->get('s');

        $newSearch = ' ((';
        $searchLines = [];

        foreach ($columns as $column) {
            $searchLines[] = '(' . $wpdb->prepare("{$wpdb->posts}.{$column} LIKE %s", '%' . $wpdb->esc_like($searchTerm) . '%') . ')';
        }

        $newSearch .= implode(' OR ', $searchLines);
        $newSearch .= '))';

        return $newSearch;
    }

    public function orderByTaxonomyTerms(array $clauses, \WP_Query $wpQuery)
    {
        global $wpdb;

        if (
            !isset($wpQuery->query['orderby']) ||
            !is_string($wpQuery->query['orderby']) ||
            !str_starts_with($wpQuery->query['orderby'], QueryBuilder::TAX_PREFIX)
        ) {
            return $clauses;
        }

        $taxonomyName = ltrim($wpQuery->query['orderby'], QueryBuilder::TAX_PREFIX);

        $clauses['join'] .= "
        LEFT OUTER JOIN {$wpdb->term_relationships} AS rel2 ON {$wpdb->posts}.ID = rel2.object_id
        LEFT OUTER JOIN {$wpdb->term_taxonomy} AS tax2 ON rel2.term_taxonomy_id = tax2.term_taxonomy_id
        LEFT OUTER JOIN {$wpdb->terms} USING (term_id)
    ";

        $clauses['where'] .= " AND (taxonomy = '{$taxonomyName}' OR taxonomy IS NULL)";
        $clauses['groupby'] = 'rel2.object_id';
        $clauses['orderby'] =
            "GROUP_CONCAT({$wpdb->terms}.name ORDER BY name ASC) " . (strtoupper($wpQuery->get('order')) == 'ASC' ? 'ASC' : 'DESC');

        return $clauses;
    }
}
