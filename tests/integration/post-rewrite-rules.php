<?php

// Pas de declare(strict_types=1) : WP-CLI eval-file le refuse (« must be the very first statement »).

use Illuminate\Support\Facades\Config;

require __DIR__ . '/bootstrap.php';

/*
 * Règles de réécriture des articles avec le préfixe posts.prefix (PostHooks::add_rewrite_rules).
 *
 * La règle ajoutée devant celles de WordPress ne doit capter que /<prefix>/<nom>/. Les chemins plus longs
 * retombent sur les règles de WordPress. Le contrôle résout de vraies URL (WP::parse_request puis WP_Query)
 * sur 2 structures, avec category_base vide :
 * - /%postname%/ : le préfixe ne vient que du filtre post_link, les catégories sont sous /category/ ;
 * - /<prefix>/%postname%/ (cas d'Aster) : les catégories sont sous /<prefix>/category/.
 * Les URL viennent des fonctions de lien du cœur (permalien, catégorie, flux, embed).
 * La taxonomie category est rendue publique et les filtres qui retirent /category/ sont mis de côté pendant le contrôle.
 * Les données de test sont supprimées, la structure, category_base et les règles sont restaurées à la fin.
 */

$prefix = Config::get('posts.prefix');

if (!$prefix) {
    echo 'posts.prefix absent : contrôle sans objet (régler le préfixe dans config/posts.php).' . PHP_EOL;
    exit(0);
}

/**
 * Rend la taxonomie category publique, avec la réécriture du cœur, même si le thème la désactive en front
 * (Immoval le fait par register_taxonomy_args). Le filtre est retiré à la fin du contrôle.
 */
function hz_public_category_args(array $args, string $taxonomy): array
{
    global $wp_rewrite;

    if ($taxonomy !== 'category') {
        return $args;
    }

    $base = get_option('category_base');

    return array_merge($args, [
        'public' => true,
        'publicly_queryable' => true,
        'query_var' => 'category_name',
        'rewrite' => [
            'hierarchical' => true,
            'slug' => $base ?: 'category',
            'with_front' => !$base || $wp_rewrite->using_index_permalinks(),
            'ep_mask' => EP_CATEGORIES,
        ],
    ]);
}

/**
 * Applique une structure et une base de catégorie, puis régénère les règles comme après un enregistrement des réglages.
 * $permastructs remet les permastructs de départ, dans leur ordre, avant la régénération.
 */
function hz_apply_permalinks(string $structure, string $categoryBase, ?array $permastructs = null): void
{
    global $wp_rewrite;

    $wp_rewrite->set_permalink_structure($structure);
    $wp_rewrite->set_category_base($categoryBase);
    // La permastruct des catégories garde le « front » de son enregistrement : on la recrée avec la nouvelle structure.
    create_initial_taxonomies();

    // register_taxonomy() ne retire pas une permastruct quand la réécriture repasse à false.
    if ($permastructs !== null) {
        $wp_rewrite->extra_permastructs = $permastructs;
    }

    // rewrite_rules() cumule les règles des permastructs dans extra_rules_top à chaque régénération du processus.
    $wp_rewrite->extra_rules_top = $GLOBALS['hz_original']['extra_rules_top'];
    flush_rewrite_rules(false);
}

/** Résout une URL comme une requête front : règle trouvée, variables de requête et requête principale. */
function hz_resolve(string $url): array
{
    $path = (string) wp_parse_url($url, PHP_URL_PATH);
    $server = $_SERVER;
    $_SERVER['REQUEST_URI'] = $path;
    unset($_SERVER['PATH_INFO']);

    $wp = new WP();
    $wp->parse_request();
    $query = new WP_Query();
    $query->query($wp->query_vars);

    $_SERVER = $server;

    return [$wp, $query, $path];
}

function hz_label(WP $wp, string $path): string
{
    return sprintf('%s (règle %s, %s)', $path, $wp->matched_rule ?? 'aucune', http_build_query($wp->query_vars));
}

/** L'URL donne l'article de test, avec un contrôle supplémentaire sur la requête. */
function hz_check_post(string $url, int $postId, string $label, ?callable $extra = null): void
{
    [$wp, $query, $path] = hz_resolve($url);
    $ok = $query->is_single() && $query->post instanceof WP_Post && $query->post->ID === $postId;

    hz_check($ok && ($extra === null || $extra($query)), "$label : " . hz_label($wp, $path));
}

/** L'URL donne l'archive de la catégorie de test. */
function hz_check_category(string $url, int $termId, string $label, ?callable $extra = null): void
{
    [$wp, $query, $path] = hz_resolve($url);
    $ok = $query->is_category() && $query->get_queried_object_id() === $termId;

    hz_check($ok && ($extra === null || $extra($query)), "$label : " . hz_label($wp, $path));
}

$GLOBALS['hz_original'] = [
    'permalink_structure' => get_option('permalink_structure'),
    'category_base' => get_option('category_base'),
    'rewrite_rules' => get_option('rewrite_rules'),
    'category_rewrite' => get_taxonomy('category')->rewrite,
    'permastructs' => $GLOBALS['wp_rewrite']->extra_permastructs,
    'extra_rules_top' => $GLOBALS['wp_rewrite']->extra_rules_top,
];
$GLOBALS['hz_ids'] = ['posts' => [], 'terms' => []];

// Les extensions SEO qui retirent /category/ des liens (SEOPress, Yoast) passent par ces filtres. Ils sont mis de côté
// le temps du contrôle, pour garder les URL de catégorie du cœur (cas d'Aster), puis remis tels quels.
$GLOBALS['hz_saved_filters'] = [];

foreach (['category_rewrite_rules', 'category_link', 'term_link'] as $hook) {
    $GLOBALS['hz_saved_filters'][$hook] = $GLOBALS['wp_filter'][$hook] ?? null;
    unset($GLOBALS['wp_filter'][$hook]);
}

add_filter('register_taxonomy_args', 'hz_public_category_args', PHP_INT_MAX, 2);

try {
    $term = wp_insert_term('HZ test règle catégorie', 'category', ['slug' => 'hz-test-regle-categorie']);

    if (is_wp_error($term)) {
        throw new RuntimeException('Création de la catégorie de test : ' . $term->get_error_message());
    }

    $termId = (int) $term['term_id'];
    $GLOBALS['hz_ids']['terms'][] = $termId;

    $postId = wp_insert_post(
        [
            'post_type' => 'post',
            'post_status' => 'publish',
            'post_title' => 'HZ test règle article',
            'post_name' => 'hz-test-regle-article',
            'post_category' => [$termId],
        ],
        true
    );

    if (is_wp_error($postId)) {
        throw new RuntimeException('Création de l’article de test : ' . $postId->get_error_message());
    }

    $GLOBALS['hz_ids']['posts'][] = $postId;

    // Page dont le chemin est le préfixe (page des articles d'un site), pour sa pagination /<prefix>/page/2/.
    if (!get_page_by_path($prefix)) {
        $pageId = wp_insert_post(
            ['post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'HZ test', 'post_name' => $prefix],
            true
        );

        if (is_wp_error($pageId)) {
            throw new RuntimeException('Création de la page de test : ' . $pageId->get_error_message());
        }

        $GLOBALS['hz_ids']['posts'][] = $pageId;
    }

    $structures = [
        'Structure /%postname%/' => '/%postname%/',
        "Structure /$prefix/%postname%/" => "/$prefix/%postname%/",
    ];

    foreach ($structures as $context => $structure) {
        echo PHP_EOL . $context . PHP_EOL;
        hz_apply_permalinks($structure, '');

        $prefixInStructure = $structure !== '/%postname%/';
        $permalink = get_permalink($postId);
        $categoryLink = get_category_link($termId);

        hz_check($permalink === home_url("/$prefix/hz-test-regle-article/"), "Permalien préfixé : $permalink");
        hz_check(
            $categoryLink === home_url(($prefixInStructure ? "/$prefix" : '') . '/category/hz-test-regle-categorie/'),
            "Lien de la catégorie : $categoryLink"
        );

        hz_check_post($permalink, $postId, 'Article');
        hz_check_post(untrailingslashit($permalink), $postId, 'Article sans barre finale');
        hz_check_post(
            get_post_comments_feed_link($postId),
            $postId,
            'Flux des commentaires de l’article',
            fn(WP_Query $q): bool => $q->is_feed()
        );
        hz_check_post(
            trailingslashit($permalink) . 'feed/rss2/',
            $postId,
            'Flux RSS2 de l’article',
            fn(WP_Query $q): bool => $q->get('feed') === 'rss2'
        );
        hz_check_post(get_post_embed_url($postId), $postId, 'Embed de l’article', fn(WP_Query $q): bool => $q->is_embed());
        hz_check_post(
            trailingslashit($permalink) . $GLOBALS['wp_rewrite']->comments_pagination_base . '-2/',
            $postId,
            'Page 2 des commentaires',
            fn(WP_Query $q): bool => (int) $q->get('cpage') === 2
        );

        hz_check_category($categoryLink, $termId, 'Archive de la catégorie');
        hz_check_category(get_category_feed_link($termId), $termId, 'Flux de la catégorie', fn(WP_Query $q): bool => $q->is_feed());
        hz_check_category(
            trailingslashit($categoryLink) . 'page/2/',
            $termId,
            'Page 2 de la catégorie',
            fn(WP_Query $q): bool => (int) $q->get('paged') === 2
        );

        if (!$prefixInStructure) {
            // Lien du cœur sans préfixe, toujours résolu (redirect_canonical renvoie ensuite vers le lien préfixé).
            hz_check_post(home_url('/hz-test-regle-article/'), $postId, 'Article sans préfixe');

            // Avec cette structure, WordPress résout /<prefix>/page/2/ comme la page 2 de la page /<prefix>/.
            [$wp, $query, $path] = hz_resolve(home_url("/$prefix/page/2/"));
            hz_check(
                ($wp->query_vars['pagename'] ?? null) === $prefix && (int) ($wp->query_vars['paged'] ?? 0) === 2,
                'Page 2 de la page /' . $prefix . '/ : ' . hz_label($wp, $path)
            );
        }
    }
} catch (Throwable $e) {
    hz_check(false, 'Exception : ' . $e->getMessage());
} finally {
    // Données supprimées avant de régénérer les règles : SEOPress inscrit chaque slug de catégorie dans les siennes.
    foreach ($GLOBALS['hz_ids']['posts'] as $id) {
        wp_delete_post($id, true);
    }

    foreach ($GLOBALS['hz_ids']['terms'] as $id) {
        wp_delete_term($id, 'category');
    }

    foreach ($GLOBALS['hz_saved_filters'] as $hook => $callbacks) {
        if ($callbacks !== null) {
            $GLOBALS['wp_filter'][$hook] = $callbacks;
        }
    }

    $original = $GLOBALS['hz_original'];
    remove_filter('register_taxonomy_args', 'hz_public_category_args', PHP_INT_MAX);
    hz_apply_permalinks($original['permalink_structure'], $original['category_base'], $original['permastructs']);

    echo PHP_EOL . 'Remise en état' . PHP_EOL;

    $ids = $GLOBALS['hz_ids'];
    $leftPosts = array_filter($ids['posts'], fn(int $id): bool => get_post($id) !== null);
    $leftTerms = array_filter($ids['terms'], fn(int $id): bool => get_term($id) instanceof WP_Term);
    hz_check(
        $leftPosts === [] && $leftTerms === [],
        sprintf('Nettoyage : %d contenu(s) et %d catégorie(s) de test supprimé(s)', count($ids['posts']), count($ids['terms']))
    );
    hz_check(
        get_option('permalink_structure') === $original['permalink_structure'],
        'Structure restaurée : ' . $original['permalink_structure']
    );
    hz_check(
        get_option('category_base') === $original['category_base'],
        'category_base restaurée : « ' . $original['category_base'] . ' »'
    );
    hz_check(get_taxonomy('category')->rewrite === $original['category_rewrite'], 'Réécriture de la taxonomie category restaurée');
    $rules = (array) get_option('rewrite_rules');
    $before = (array) $original['rewrite_rules'];
    hz_check(
        $rules === $before,
        sprintf(
            'Règles de réécriture identiques à celles du départ (%d ajoutée(s) : %s ; %d retirée(s) : %s)',
            count(array_diff_key($rules, $before)),
            implode(', ', array_slice(array_keys(array_diff_key($rules, $before)), 0, 5)),
            count(array_diff_key($before, $rules)),
            implode(', ', array_slice(array_keys(array_diff_key($before, $rules)), 0, 5))
        )
    );
}

hz_done();
