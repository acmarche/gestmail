<?php

declare(strict_types=1);

namespace App\Support;

use const PASSWORD_ARGON2ID;

use RuntimeException;

final class DovecotPassword
{
    private const SCHEME = '{ARGON2ID}';

    /**
     * Argon2id parameters. Dovecot reads them back from the PHC string, so they
     * can be raised later without invalidating existing hashes.
     *
     * The memory cost is deliberately below PHP's 64 MiB default: Dovecot
     * re-verifies on every IMAP/POP/SMTP login, and each auth worker allocates
     * this much for the duration of the check.
     *
     * @var array{memory_cost: int, time_cost: int, threads: int}
     */
    private const OPTIONS = [
        'memory_cost' => 32768,
        'time_cost' => 4,
        'threads' => 1,
    ];

    /**
     * Hash a clear-text password as Argon2id, prefixed with the Dovecot scheme
     * tag so Dovecot detects it regardless of default_pass_scheme.
     */
    public static function hash(string $password): string
    {
        $hash = password_hash($password, PASSWORD_ARGON2ID, self::OPTIONS);

        if (! str_starts_with($hash, '$argon2id$')) {
            throw new RuntimeException('Argon2id hashing failed.');
        }

        return self::SCHEME.$hash;
    }

    /**
     * Verify a clear-text password against a stored hash, like Dovecot does: the
     * scheme tag decides how, and a hash without one is taken as Argon2id.
     *
     * Besides Argon2id, these are the legacy schemes found in virtual_users. {MD5}
     * is base64, as in LDAP. An unknown scheme never matches.
     */
    public static function check(string $password, string $stored): bool
    {
        [$scheme, $hash] = self::parse($stored);

        return match ($scheme) {
            '', 'ARGON2ID' => password_verify($password, $hash),
            'CRYPT' => hash_equals($hash, crypt($password, $hash)),
            'MD5' => hash_equals((string) base64_decode($hash, true), md5($password, true)),
            'SHA' => hash_equals((string) base64_decode($hash, true), sha1($password, true)),
            'SSHA' => self::checkSaltedSha($password, (string) base64_decode($hash, true)),
            default => false,
        };
    }

    /**
     * @return array{string, string} The uppercase scheme, empty when there is none, and the hash.
     */
    private static function parse(string $stored): array
    {
        if (preg_match('/^\{([^}]+)\}(.*)$/s', $stored, $matches) === 1) {
            return [mb_strtoupper($matches[1]), $matches[2]];
        }

        return ['', $stored];
    }

    /**
     * The decoded value is the 20 bytes of the SHA-1 digest followed by the salt. It is
     * binary, hence the 8bit encoding.
     */
    private static function checkSaltedSha(string $password, string $decoded): bool
    {
        return hash_equals(mb_substr($decoded, 0, 20, '8bit'), sha1($password.mb_substr($decoded, 20, null, '8bit'), true));
    }
}
