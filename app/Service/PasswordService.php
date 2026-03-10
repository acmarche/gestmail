<?php

declare(strict_types=1);

namespace App\Service;

final class PasswordService
{
    public static function passwordStrength(?string $password): int
    {
        if (blank($password)) {
            return 0;
        }

        $score = 0;

        if (mb_strlen($password) >= 8) {
            $score++;
        }

        if (preg_match('/[a-z]/', $password) && preg_match('/[A-Z]/', $password)) {
            $score++;
        }

        if (preg_match('/\d/', $password)) {
            $score++;
        }

        if (preg_match('/[^a-zA-Z\d]/', $password)) {
            $score++;
        }

        if (mb_strlen($password) >= 12) {
            $score++;
        }

        return $score;
    }

    public static function passwordStrengthLabel(?string $password): string
    {
        return match (self::passwordStrength($password)) {
            0 => '',
            1 => 'Très faible',
            2 => 'Faible',
            3 => 'Moyen',
            4 => 'Fort',
            5 => 'Très fort',
        };
    }

    public static function passwordStrengthColor(?string $password): string
    {
        return match (self::passwordStrength($password)) {
            1, 2 => 'danger',
            3 => 'warning',
            4, 5 => 'success',
            default => 'gray',
        };
    }
}
