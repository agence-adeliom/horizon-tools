<?php

declare(strict_types=1);

namespace Adeliom\HorizonTools\Hooks;

use Adeliom\HorizonTools\Database\QueryBuilder;

class QueryBuilderHooks extends AbstractHook
{
    public function init(): void
    {
        add_filter('posts_clauses', [$this, 'orderByTaxonomyTerms'], accepted_args: 2);
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
