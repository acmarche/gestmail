<?php

declare(strict_types=1);

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Database\Eloquent\ComparesCastableAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Encrypts a personal data column of the citoyens table with the key shared with the
 * intranet (app.citoyen_data_key), not with APP_KEY, which differs between the two
 * applications. The intranet's EmailManagement module uses the same cast.
 *
 * Each encryption gives a new ciphertext, so the stored values are compared decrypted: an
 * unchanged value does not make the model dirty.
 *
 * @implements CastsAttributes<string|null, string|null>
 */
final class EncryptedPersonalDataCast implements CastsAttributes, ComparesCastableAttributes
{
    public static function encrypter(): Encrypter
    {
        $key = config('app.citoyen_data_key');

        if (blank($key)) {
            throw new RuntimeException('CITOYEN_DATA_KEY is not set: the citoyens personal data cannot be encrypted.');
        }

        return new Encrypter(base64_decode(Str::after($key, 'base64:')), config('app.cipher'));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return $value === null ? null : self::encrypter()->decryptString($value);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return blank($value) ? null : self::encrypter()->encryptString((string) $value);
    }

    public function compare(Model $model, string $key, mixed $firstValue, mixed $secondValue): bool
    {
        return $this->get($model, $key, $firstValue, []) === $this->get($model, $key, $secondValue, []);
    }
}
