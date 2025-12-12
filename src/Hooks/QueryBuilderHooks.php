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
        add_filter('posts_clauses', [$this, 'handleLatLngQuery'], accepted_args: 4);
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

    public function handleLatLngQuery(array $clauses, \WP_Query $query)
    {
        global $wpdb;

        if (!isset($query->query_vars['lat_lng_query']) || !is_array($query->query_vars['lat_lng_query'])) {
            return $clauses;
        }

        $latLngParams = $query->query_vars['lat_lng_query'];

        foreach ($latLngParams as $key1 => $geoParams) {
            $relation = $geoParams['relation'] ?? 'AND';

            unset($geoParams['relation']);
            $geoParams = array_values($geoParams);

            foreach ($geoParams as $key2 => $geoParam) {
                if (!isset($geoParam['latitude']['value'], $geoParam['longitude']['value'], $geoParam['radius_in_km'])) {
                    continue;
                }

                $lat_point = floatval($geoParam['latitude']['value']);
                $lon_point = floatval($geoParam['longitude']['value']);
                $radius_km = floatval($geoParam['radius_in_km']);
                $earth_radius = 6371; // Rayon de la Terre en kilomètres

                $mt1 = sprintf('mt1_%s_%s', $key1, $key2);
                $mt2 = sprintf('mt2_%s_%s', $key1, $key2);

                // 2. CONSTRUIRE LA CLAUSE WHERE DE DISTANCE HAVERSINE
                // Nous utilisons la formule Haversine (distance_km = 2 * R * asin(sqrt(...)))
                // où 2 * R (le diamètre) = 12742 km.
                $distance_sql =
                    $earth_radius * 2 .
                    " * ASIN(
        SQRT(
            POW(SIN(RADIANS({$lat_point} - $mt1.meta_value) / 2), 2) +
            COS(RADIANS({$lat_point})) * COS(RADIANS($mt1.meta_value)) *
            POW(SIN(RADIANS({$lon_point} - $mt2.meta_value) / 2), 2)
        )
    )";

                // 3. AJOUTER LES JOINTURES NÉCESSAIRES (JOIN)
                // Les coordonnées sont stockées en tant que Meta Données de Post (postmeta)

                $clauses['join'] .= "
        INNER JOIN {$wpdb->postmeta} AS $mt1 ON ( {$wpdb->posts}.ID = $mt1.post_id )
        INNER JOIN {$wpdb->postmeta} AS $mt2 ON ( {$wpdb->posts}.ID = $mt2.post_id )
    ";

                // 4. AJOUTER LA CLAUSE WHERE
                // Nous vérifions que les meta_keys correspondent à la latitude et la longitude stockées

                $clauses['where'] .= $wpdb->prepare(
                    "
        AND ( $mt1.meta_key = 'latitude' AND $mt2.meta_key = 'longitude' )
        AND ( {$distance_sql} <= %f )
    ",
                    $radius_km
                );

                // 5. AJOUTER LA CLAUSE ORDERBY (Tri par distance la plus courte)
                $orderByDistance = $geoParam['order_by_distance'] ?? false;
                $order = $geoParam['order'] ?? 'ASC';

                if ($orderByDistance) {
                    $clauses['orderby'] = " {$distance_sql} {$order}, " . $clauses['orderby'];
                }

                // 6. DÉ-DOUBLONNAGE
                // Pour s'assurer qu'un post n'apparaît qu'une seule fois à cause des multiples JOINs
                $clauses['fields'] .= ", ($distance_sql) AS distance_km";
                $clauses['groupby'] = "{$wpdb->posts}.ID";
            }
        }

        return $clauses;
    }
}
