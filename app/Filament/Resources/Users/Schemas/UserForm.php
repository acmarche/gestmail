<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users\Schemas;

use App\Input\PasswordInput;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

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
                            ->required()
                            ->maxLength(255),
                        TextInput::make('last_name')
                            ->label('Nom')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('username')
                            ->label('Nom d\'utilisateur')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                    ]),
                Section::make('Compte')
                    ->components(
                        PasswordInput::create(),
                    ),
            ]);
    }
}
