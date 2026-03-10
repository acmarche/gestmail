<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Filament\Resources\Citoyens\CitoyenResource;
use App\Models\Citoyen;
use App\Repository\CitoyenRepository;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

final class ExpiredAccountsWidget extends TableWidget
{
    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(fn(): Builder => CitoyenRepository::getExpiredQuery()->orderBy('last_connection', 'asc'))
            ->defaultPaginationPageOption(20)
            ->heading(fn(): string => CitoyenRepository::getExpiredQuery()->count().' comptes à supprimer')
            ->description('Comptes dont la dernière connexion date de plus de 2 ans ou sans aucune connexion.')
            ->columns([
                TextColumn::make('mail')
                    ->label('Email')
                    ->searchable()
                    ->url(fn(Citoyen $record): string => CitoyenResource::getUrl('view', ['record' => $record])),
                TextColumn::make('sn')
                    ->label('Nom'),
                TextColumn::make('givenName')
                    ->label('Prénom'),
                TextColumn::make('l')
                    ->label('Localité'),
                TextColumn::make('last_connection')
                    ->label('Dernière connexion')
                    ->date()
                    ->placeholder('Jamais'),
            ]);
    }
}
