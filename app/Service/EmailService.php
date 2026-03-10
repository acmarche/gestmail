<?php

declare(strict_types=1);

namespace App\Service;

use Transliterator;

final class EmailService
{
    /**
     * Sanitize a string for use in email local part (RFC 3696 compliant).
     */
    public static function sanitizeForEmail(string $value): string
    {
        $value = mb_strtolower($value);

        $transliterator = Transliterator::create('NFD; [:Nonspacing Mark:] Remove; NFC');
        if ($transliterator) {
            $value = $transliterator->transliterate($value);
        }

        $value = preg_replace('/[^a-z0-9._-]/', '', $value);
        $value = preg_replace('/\.{2,}/', '.', $value);

        return mb_trim($value, '.-');
    }
}
