<?php

declare(strict_types=1);

namespace App\Filament\Resources\Citoyens\Schemas;

use App\Service\PasswordService;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Validation\Rules\Password;

final class PasswordForm
{
    public static function configure(): array
    {
        return [
            TextInput::make('password')
                ->label('Nouveau mot de passe')
                ->helperText('min 10 caractères, minuscule, majuscule et nombre')
                ->password()
                ->revealable()
                ->required()
                ->rule(Password::min(10)->letters()->mixedCase()->numbers())
                ->live(debounce: 500)
                ->afterContent(
                    Text::make(
                        fn (Get $get): string => PasswordService::passwordStrengthLabel($get('password'))
                    )
                        ->color(
                            fn (Get $get): string => PasswordService::passwordStrengthColor($get('password'))
                        ),
                ),
            TextInput::make('password_confirmation')
                ->label('Confirmer le mot de passe')
                ->password()
                ->revealable()
                ->required()
                ->same('password'),
        ];
    }

    public static function quota(): array
    {
        return [
            TextInput::make('gosaMailQuota')
                ->label('Quota mail')
                ->helperText('min 150MB, max 4000MB')
                ->numeric()
                ->minValue(150)
                ->maxValue(4000)
                ->required()
                ->suffix('MB'),
        ];

    }
}
