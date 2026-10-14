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

delete_option('options_hz_repo_check');
delete_option('_options_hz_repo_check');
delete_option('hz_repo_plain');
delete_post_meta($postId, 'hz_repo_check');
hz_done();
