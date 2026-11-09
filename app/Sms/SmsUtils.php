<?php

declare(strict_types=1);

namespace App\Sms;

final class SmsUtils
{
    /**
     * Normalise un numéro BE au format international sans "+" (ex: 32476662615).
     */
    public static function cleanPhoneNumber(string $phone): string
    {
        $number = preg_replace('/\D+/', '', $phone) ?? '';

        if (str_starts_with($number, '320')) {
            $number = '32'.mb_substr($number, 3);
        }

        if (str_starts_with($number, '0')) {
            $number = mb_substr($number, 1);
        }

        if (! str_starts_with($number, '32')) {
            $number = '32'.$number;
        }

        return $number;
    }
}
