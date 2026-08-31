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
     * Verify a clear-text password against a stored Argon2id hash, with or
     * without the leading scheme tag.
     *
     * Only the current scheme is handled. Dovecot performs the actual
     * authentication and detects any other scheme from its own prefix.
     */
    public static function check(string $password, string $stored): bool
    {
        return password_verify($password, self::stripScheme($stored));
    }

    private static function stripScheme(string $stored): string
    {
        return str_starts_with($stored, self::SCHEME)
            ? mb_substr($stored, mb_strlen(self::SCHEME))
            : $stored;
    }
}
