<?php

declare(strict_types=1);

namespace App\Filament\Resources\Citoyens\Schemas;

use App\Input\PasswordInput;
use Filament\Forms\Components\TextInput;

final class PasswordForm
{
    public static function configure(): array
    {
        return PasswordInput::create();
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
