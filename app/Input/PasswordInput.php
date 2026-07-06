<?php

declare(strict_types=1);

namespace App\Input;

use App\Service\PasswordService;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

final class PasswordInput
{
    public static function create(): array
    {
        return
            [
                TextInput::make('password')
                    ->label('Mot de passe')
                    ->password()
                    ->helperText('min 12 caractères, minuscule, majuscule et nombre')
                    ->revealable()
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->dehydrated(fn (?string $state): bool => filled($state))
                    ->rule(Password::defaults())
                    ->live(debounce: 500)
                    ->afterContent(
                        Action::make('generatePassword')
                            ->label('Générer')
                            ->icon(Heroicon::Sparkles)
                            ->color('gray')
                            ->action(function (Set $schemaSet): void {
                                $schemaSet('password', Str::password(12, symbols: false));
                            }),
                    ),
                Text::make(
                    fn (Get $get): string => PasswordService::passwordStrengthLabel($get('password'))
                )
                    ->color(
                        fn (Get $get): string => PasswordService::passwordStrengthColor($get('password'))
                    ),
            ];
    }
}
