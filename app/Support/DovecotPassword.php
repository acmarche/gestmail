<?php

declare(strict_types=1);

namespace App\Support;

use RuntimeException;

final class DovecotPassword
{
    private const SCHEME = '{SHA512-CRYPT}';

    /**
     * Hash a clear-text password as SHA512-CRYPT, prefixed with the Dovecot
     * scheme tag so Dovecot detects it regardless of default_pass_scheme.
     */
    public static function hash(string $password): string
    {
        $salt = mb_substr(strtr(base64_encode(random_bytes(12)), '+', '.'), 0, 16);
        $hash = crypt($password, '$6$'.$salt.'$');

        if (! str_starts_with($hash, '$6$')) {
            throw new RuntimeException('SHA512-CRYPT hashing failed.');
        }

        return self::SCHEME.$hash;
    }

    /**
     * Verify a clear-text password against a stored SHA512-CRYPT hash,
     * with or without the leading {SHA512-CRYPT} scheme tag.
     */
    public static function check(string $password, string $stored): bool
    {
        $crypt = str_starts_with($stored, self::SCHEME)
            ? mb_substr($stored, mb_strlen(self::SCHEME))
            : $stored;

        return hash_equals($crypt, crypt($password, $crypt));
    }
}
