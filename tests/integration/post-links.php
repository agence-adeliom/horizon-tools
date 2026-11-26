<?php

// Pas de declare(strict_types=1) : WP-CLI eval-file le refuse (« must be the very first statement »).

use Illuminate\Support\Facades\Config;

require __DIR__ . '/bootstrap.php';

/*
 * Liens des articles avec le préfixe posts.prefix (PostHooks::change_blog_links).
 *
 * Un article publié prend l'URL /<prefix>/<post_name>/. Un article que WordPress ne traite pas
 * encore comme publié (brouillon, en attente, brouillon automatique, planifié) garde le lien du
 * cœur, ?p=<ID> : sans post_name, le préfixe donnait /<prefix>// et l'aperçu tombait en 404.
 * Le permalien modifiable de l'éditeur (get_sample_permalink) garde le préfixe et %postname%.
 * Les articles de test sont supprimés à la fin.
 */

$prefix = Config::get('posts.prefix');

if (!$prefix) {
    echo 'posts.prefix absent : contrôle sans objet (régler le préfixe dans config/posts.php).' . PHP_EOL;
    exit(0);
}

// eval-file exécute le fichier dans une fonction : la liste passe par $GLOBALS, pas par une variable de premier niveau.
$GLOBALS['hz_post_ids'] = [];

function hz_create_post(array $args): int
{
    $id = wp_insert_post(array_merge(['post_type' => 'post', 'post_title' => 'HZ test liens articles'], $args), true);

    if (is_wp_error($id)) {
        hz_check(false, 'Création de l’article de test : ' . $id->get_error_message());
        return 0;
    }

    $GLOBALS['hz_post_ids'][] = $id;

    return $id;
}

/** Contrôle qu'un article non publié garde le lien du cœur, aperçu compris. */
function hz_check_unpublished(int $id, string $context): void
{
    $expected = home_url('?p=' . $id);
    $link = get_permalink($id);

    hz_check($link === $expected, "$context : get_permalink() garde le lien du cœur ($expected), obtenu $link");
    hz_check(!str_contains((string) preg_replace('#^https?://#', '', (string) $link), '//'), "$context : pas de // dans le lien");

    $preview = get_preview_post_link($id);
    hz_check(
        str_starts_with((string) $preview, $expected) && str_contains((string) $preview, 'preview=true'),
        "$context : lien d’aperçu sur ?p=$id, obtenu $preview"
    );
}

try {
    $context = 'Brouillon sans post_name';
    $draft = hz_create_post(['post_status' => 'draft']);
    hz_check(get_post_field('post_name', $draft) === '', "$context : post_name vide");
    hz_check_unpublished($draft, $context);

    $sample = get_sample_permalink($draft);
    hz_check(
        $sample[0] === home_url(sprintf('/%s/%%postname%%/', $prefix)),
        "$context : permalien modifiable de l’éditeur sur /$prefix/%postname%/, obtenu {$sample[0]}"
    );
    hz_check($sample[1] === 'hz-test-liens-articles', "$context : nom proposé par l’éditeur tiré du titre");

    $context = 'Article en attente sans post_name';
    hz_check_unpublished(hz_create_post(['post_status' => 'pending']), $context);

    $context = 'Brouillon automatique';
    hz_check_unpublished(hz_create_post(['post_status' => 'auto-draft', 'post_title' => 'Brouillon automatique']), $context);

    $context = 'Brouillon avec post_name';
    $named = hz_create_post(['post_status' => 'draft', 'post_name' => 'hz-test-brouillon-nomme']);
    hz_check(get_post_field('post_name', $named) === 'hz-test-brouillon-nomme', "$context : post_name conservé");
    hz_check_unpublished($named, $context);

    $context = 'Article planifié';
    $future = hz_create_post([
        'post_status' => 'future',
        'post_name' => 'hz-test-planifie',
        'post_date' => gmdate('Y-m-d H:i:s', time() + YEAR_IN_SECONDS),
    ]);
    hz_check(get_post_status($future) === 'future', "$context : statut future");
    hz_check_unpublished($future, $context);

    $context = 'Article publié';
    $published = hz_create_post(['post_status' => 'publish', 'post_name' => 'hz-test-publie']);
    $expected = home_url(sprintf('/%s/hz-test-publie/', $prefix));
    hz_check(get_permalink($published) === $expected, "$context : lien préfixé ($expected), obtenu " . get_permalink($published));
    hz_check(
        get_permalink($published, true) === home_url(sprintf('/%s/%%postname%%/', $prefix)),
        "$context : leavename garde %postname% dans le lien préfixé"
    );
} finally {
    $ids = $GLOBALS['hz_post_ids'];

    foreach ($ids as $id) {
        wp_delete_post($id, true);
    }

    $left = array_filter($ids, fn(int $id): bool => get_post($id) !== null);
    hz_check($ids !== [] && $left === [], sprintf('Nettoyage : %d article(s) de test supprimé(s)', count($ids)));
}

hz_done();
