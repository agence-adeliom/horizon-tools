<?php

declare(strict_types=1);

use Adeliom\HorizonTools\Services\Encryption\EncryptedValueCodec;
use Illuminate\Encryption\Encrypter;

require __DIR__ . '/bootstrap.php';

$codec = EncryptedValueCodec::make();
hz_check($codec !== null, 'APP_KEY présente : codec disponible');

$stored = $codec->encrypt('sk_test_123');
hz_check(str_starts_with($stored, 'hz_enc:v1:'), 'valeur chiffrée préfixée');
hz_check(!str_contains($stored, 'sk_test_123'), 'le clair n’apparaît pas dans la valeur stockée');
hz_check($codec->decrypt($stored) === 'sk_test_123', 'aller-retour chiffrement / déchiffrement');
hz_check($codec->encrypt($stored) === $stored, 'pas de double chiffrement');
hz_check($codec->encrypt('') === '', 'chaîne vide laissée vide');
hz_check($codec->encrypt('a') !== $codec->encrypt('a'), 'IV aléatoire : deux chiffrements distincts');
hz_check($codec->decrypt('valeur-en-clair') === 'valeur-en-clair', 'valeur héritée en clair lue telle quelle');
hz_check(
    EncryptedValueCodec::isEncrypted($stored) && !EncryptedValueCodec::isEncrypted('x') && !EncryptedValueCodec::isEncrypted(null),
    'détection du préfixe'
);

$otherKey = new EncryptedValueCodec(new Encrypter(Encrypter::generateKey('AES-256-CBC'), 'AES-256-CBC'));
hz_check($otherKey->decrypt($stored) === null, 'autre clé : indéchiffrable, renvoie null');
hz_check($codec->decrypt('hz_enc:v1:corrompu') === null, 'payload corrompu : renvoie null');

hz_done();
