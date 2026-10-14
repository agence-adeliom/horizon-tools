<?php

// Pas de declare(strict_types=1) : WP-CLI eval-file le refuse (« must be the very first statement »).

use Adeliom\HorizonTools\Services\Encryption\EncryptedValueCodec;
use Adeliom\HorizonTools\Services\Encryption\EncryptedValueRepository;
use Adeliom\HorizonTools\Services\Encryption\StoredEncryptedValue;

require __DIR__ . '/bootstrap.php';

$codec = EncryptedValueCodec::make();
$repo = new EncryptedValueRepository();
$postId = (int) get_posts(['post_type' => 'any', 'numberposts' => 1, 'fields' => 'ids', 'post_status' => 'any'])[0];

update_option('options_hz_repo_check', $codec->encrypt('opt-secret'));
update_option('_options_hz_repo_check', 'field_hz_repo_check');
update_post_meta($postId, 'hz_repo_check', $codec->encrypt('meta-secret'));
update_option('hz_repo_plain', 'pas chiffré');

$found = [];
foreach ($repo->all() as $value) {
    $found[$value->location()] = $value;
}

hz_check(isset($found['options:0:options_hz_repo_check']), 'option chiffrée trouvée');
hz_check(($found['options:0:options_hz_repo_check']->reference ?? null) === 'field_hz_repo_check', 'référence ACF de l’option lue');
hz_check(isset($found['postmeta:' . $postId . ':hz_repo_check']), 'post meta chiffrée trouvée');
hz_check(!isset($found['options:0:hz_repo_plain']), 'valeur en clair ignorée');

hz_check($repo->read('options', 0, 'options_hz_repo_absent') === null, 'read() : ligne absente → null');
$repo->write('postmeta', $postId, 'hz_repo_check', 'hz_enc:v1:remplacé');
hz_check($repo->read('postmeta', $postId, 'hz_repo_check') === 'hz_enc:v1:remplacé', 'write() puis read() sur une meta');
hz_check($repo->objectExists('postmeta', $postId) && !$repo->objectExists('postmeta', 999999999), 'objectExists()');

$roundTrip = StoredEncryptedValue::fromArray($found['options:0:options_hz_repo_check']->toArray());
hz_check(
    $roundTrip->location() === 'options:0:options_hz_repo_check' && $roundTrip->reference === 'field_hz_repo_check',
    'toArray() / fromArray()'
);

/*
 * Jetons embarqués : blocs ACF de post_content (produits par acf_parse_save_blocks) et
 * option sérialisée de type widget_block.
 */
acf_register_block_type([
    'name' => 'hz-repo-check',
    'title' => 'Contrôle dépôt',
    'mode' => 'edit',
    'render_callback' => static function (): void {},
]);
acf_add_local_field_group([
    'key' => 'group_hz_repo',
    'title' => 'Contrôle dépôt',
    'fields' => [['key' => 'field_hz_repo', 'name' => 'hz_repo', 'label' => 'Clé', 'type' => 'encrypted_text']],
    'location' => [[['param' => 'block', 'operator' => '==', 'value' => 'acf/hz-repo-check']]],
]);

$attrs = wp_json_encode(
    ['name' => 'acf/hz-repo-check', 'data' => ['field_hz_repo' => 'sk/repo+secret=']],
    JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
);
$saved = wp_unslash(acf_parse_save_blocks(wp_slash('<!-- wp:acf/hz-repo-check ' . $attrs . ' /-->')));
hz_check(
    preg_match_all(EncryptedValueRepository::TOKEN_PATTERN, $saved, $matches) === 1,
    'jeton : une occurrence dans le contenu produit par ACF'
);
$token = $matches[0][0];
hz_check(str_contains($saved, '"hz_repo":"' . $token . '"'), 'jeton : expression couvre tout le jeton (aucun caractère échappé)');
hz_check($codec->decrypt($token) === 'sk/repo+secret=', 'jeton extrait déchiffrable');

$content = "<p>avant « é »</p>\n" . $saved . "\n<p>après</p>";
$contentPost = wp_insert_post(['post_title' => 'hz-repo-content', 'post_status' => 'draft', 'post_content' => wp_slash($content)]);
hz_check(is_int($contentPost) && $contentPost > 0, 'brouillon créé');

$mine = array_values(array_filter($repo->contentTokens(), static fn(array $item): bool => $item['postId'] === $contentPost));
hz_check(count($mine) === 1 && $mine[0]['token'] === $token, 'contentTokens() trouve le jeton du brouillon');

$newToken = $codec->encrypt('nouvelle-valeur');
hz_check($repo->replaceInContent($contentPost, $token, $newToken) === true, 'replaceInContent() renvoie vrai');
clean_post_cache($contentPost);
hz_check(get_post($contentPost)->post_content === str_replace($token, $newToken, $content), 'reste du contenu identique octet pour octet');
hz_check($repo->replaceInContent($contentPost, $token, $newToken) === false, 'replaceInContent() : jeton absent → faux');
wp_delete_post($contentPost, true);

$widgetToken = $codec->encrypt('widget-secret');
$widgets = [
    2 => ['content' => '<!-- wp:acf/hz-repo-check {"name":"acf/hz-repo-check","data":{"hz_repo":"' . $widgetToken . '"}} /-->'],
    '_multiwidget' => 1,
];
update_option('hz_repo_widget_check', $widgets, false);
$foundOptions = array_values(
    array_filter($repo->embeddedOptionTokens(), static fn(array $item): bool => $item['option'] === 'hz_repo_widget_check')
);
hz_check(
    count($foundOptions) === 1 && $foundOptions[0]['token'] === $widgetToken,
    'embeddedOptionTokens() trouve le jeton d’une option sérialisée'
);
hz_check(!isset($found['options:0:hz_repo_widget_check']), 'all() ne voit pas le jeton embarqué');

$newWidgetToken = $codec->encrypt('widget-secret-2');
hz_check($repo->replaceInOption('hz_repo_widget_check', $widgetToken, $newWidgetToken) === true, 'replaceInOption() renvoie vrai');
$raw = $GLOBALS['wpdb']->get_var("SELECT option_value FROM {$GLOBALS['wpdb']->options} WHERE option_name = 'hz_repo_widget_check'");
hz_check(is_array(unserialize($raw)), 'l’option reste désérialisable');
$after = get_option('hz_repo_widget_check');
hz_check(
    $after === [
        2 => ['content' => str_replace($widgetToken, $newWidgetToken, $widgets[2]['content'])],
        '_multiwidget' => 1,
    ],
    'seul le jeton a changé dans la structure'
);
delete_option('hz_repo_widget_check');

delete_option('options_hz_repo_check');
delete_option('_options_hz_repo_check');
delete_option('hz_repo_plain');
delete_post_meta($postId, 'hz_repo_check');
hz_done();
