<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users\Schemas;

use App\Service\PasswordService;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

final class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identité')
                    ->columns()
                    ->components([
                        TextInput::make('first_name')
                            ->label('Prénom')
                            ->required(),
                        TextInput::make('last_name')
                            ->label('Nom')
                            ->required(),
                        TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->required()
                            ->columnSpanFull(),
                    ]),
                Section::make('Compte')
                    ->components([
                        TextInput::make('password')
                            ->label('Mot de passe')
                            ->helperText('min 10 caractères, minuscule, majuscule et nombre')
                            ->password()
                            ->revealable()
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->dehydrated(fn (?string $state): bool => filled($state))
                            ->rule(Password::min(10)->letters()->mixedCase()->numbers())
                            ->live(debounce: 500)
                            ->afterContent(
                                Action::make('generatePassword')
                                    ->label('Générer')
                                    ->icon(Heroicon::Sparkles)
                                    ->color('gray')
                                    ->action(function (Set $schemaSet): void {
                                        $schemaSet('password', Str::password(16));
                                    }),
                            ),
                        Text::make(
                            fn (Get $get): string => PasswordService::passwordStrengthLabel($get('password'))
                        )
                            ->color(
                                fn (Get $get): string => PasswordService::passwordStrengthColor($get('password'))
                            ),
                    ]),
            ]);
    }
}
