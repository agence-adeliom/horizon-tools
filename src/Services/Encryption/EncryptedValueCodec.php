<?php

declare(strict_types=1);

namespace Adeliom\HorizonTools\Services\Encryption;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Contracts\Encryption\StringEncrypter;

/**
 * Chiffre et déchiffre les valeurs des champs « Texte chiffré ».
 *
 * S’appuie sur l’Encrypter Laravel d’Acorn (APP_KEY, AES-256-CBC + HMAC) : la clé
 * vit dans le .env, la valeur chiffrée en base. Il faut donc les deux pour lire.
 * Le préfixe versionné distingue une valeur chiffrée d’une valeur héritée en clair
 * et permet de retrouver toutes les valeurs chiffrées en base.
 */
final class EncryptedValueCodec
{
    public const PREFIX = 'hz_enc:v1:';

    public function __construct(private readonly StringEncrypter $encrypter)
    {
    }

    /**
     * Codec branché sur l’Encrypter d’Acorn, ou null si APP_KEY est absente ou
     * invalide (MissingAppKeyException / RuntimeException à la résolution).
     */
    public static function make(): ?self
    {
        try {
            return new self(app('encrypter'));
        } catch (\Throwable) {
            return null;
        }
    }

    public static function isEncrypted(mixed $value): bool
    {
        return is_string($value) && str_starts_with($value, self::PREFIX);
    }

    public function encrypt(string $plain): string
    {
        if ($plain === '' || self::isEncrypted($plain)) {
            return $plain;
        }

        return self::PREFIX . $this->encrypter->encryptString($plain);
    }

    /**
     * Valeur en clair, ou null si elle est chiffrée avec une clé inconnue
     * (ni APP_KEY ni APP_PREVIOUS_KEYS) ou corrompue.
     */
    public function decrypt(string $stored): ?string
    {
        if (!self::isEncrypted($stored)) {
            return $stored;
        }

        try {
            return $this->encrypter->decryptString(substr($stored, strlen(self::PREFIX)));
        } catch (DecryptException) {
            return null;
        }
    }

    /**
     * Vrai si la valeur est un jeton chiffré que la clé courante ne sait pas lire (autre clé ou
     * jeton corrompu). Une valeur vide, en clair ou lisible renvoie faux.
     */
    public function isUnreadableToken(string $value): bool
    {
        return self::isEncrypted($value) && $this->decrypt($value) === null;
    }
}
