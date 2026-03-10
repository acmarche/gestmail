<?php

declare(strict_types=1);

namespace App\Filament\Resources\Citoyens\Schemas;

use App\Service\EmailService;
use App\Service\PasswordService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Validation\Rules\Password;

final class CitoyenForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identité')
                    ->columns()
                    ->components([
                        TextInput::make('givenName')
                            ->label('Prénom'),
                        TextInput::make('sn')
                            ->label('Nom'),
                        TextInput::make('cn')
                            ->label('Nom complet')
                            ->columnSpanFull(),
                    ]),
                self::coordinates(),
                Section::make('Divers')
                    ->components([
                        Textarea::make('description')
                            ->label('Description')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function forCreating(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identité')
                    ->columns()
                    ->components([
                        TextInput::make('sn')
                            ->label('Nom')
                            ->required()
                            ->live(onBlur: true),
                        TextInput::make('givenName')
                            ->label('Prénom')
                            ->live(onBlur: true),
                        TextInput::make('mail')
                            ->label('Email')
                            ->email()
                            ->columnSpanFull()
                            ->required()
                            ->afterContent(
                                Action::make('generateEmail')
                                    ->label('Générer')
                                    ->icon(Heroicon::Sparkles)
                                    ->color('gray')
                                    ->action(function (Get $schemaGet, Set $schemaSet): void {
                                        $givenName = $schemaGet('givenName');
                                        $sn = $schemaGet('sn');

                                        if (blank($givenName) || blank($sn)) {
                                            return;
                                        }

                                        $schemaSet(
                                            'mail',
                                            EmailService::sanitizeForEmail(
                                                $givenName
                                            ).'.'.EmailService::sanitizeForEmail($sn).'@marche.be'
                                        );
                                    }),
                            ),
                    ]),
                self::coordinates(),
                Section::make('Compte')
                    ->columns()
                    ->components([
                        TextInput::make('userPassword')
                            ->label('Mot de passe')
                            ->helperText('min 10 catactères, minuscule, majuscule et nombre')
                            ->password()
                            ->revealable()
                            ->required()
                            ->rule(Password::min(10)->letters()->mixedCase()->numbers())
                            ->live(debounce: 500)
                            ->afterContent(
                                Text::make(
                                    fn (Get $get): string => PasswordService::passwordStrengthLabel($get('userPassword'))
                                )
                                    ->color(
                                        fn (Get $get): string => PasswordService::passwordStrengthColor(
                                            $get('userPassword')
                                        )
                                    ),
                            ),
                        TextInput::make('gosaMailQuota')
                            ->label('Quota mail')
                            ->numeric()
                            ->minValue(150)
                            ->maxValue(4000)
                            ->default(350)
                            ->suffix('MB'),
                    ]),
                Section::make('Divers')
                    ->components([
                        Textarea::make('description')
                            ->label('Description')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    private static function coordinates(): Section
    {
        return Section::make('Coordonnées')
            ->columns()
            ->components([
                TextInput::make('employeeNumber')
                    ->label('Numéro national')
                    ->required(),
                TextInput::make('postalAddress')
                    ->label('Rue et numéro')
                    ->required(),
                TextInput::make('postalCode')
                    ->label('Code postal')
                    ->default('6900')
                    ->required(),
                TextInput::make('l')
                    ->label('Localité')
                    ->required(),
            ]);
    }
}
