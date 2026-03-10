<?php

declare(strict_types=1);

namespace App\Filament\Resources\Citoyens\Tables;

use App\Models\Citoyen;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class CitoyensTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultPaginationPageOption(50)
            ->defaultSort('mail', 'asc')
            ->columns([
                TextColumn::make('mail')
                    ->label('Email')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('sn')
                    ->label('Nom')
                    ->sortable()
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('givenName')
                    ->label('Prénom')
                    ->sortable()
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('l')
                    ->label('Localité')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('last_connection')
                    ->label('Dernière connexion')
                    ->date()
                    ->sortable()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('l')
                    ->label('Localité')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('employeNumber')
                    ->label('Numéro national')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('gosaMailQuota')
                    ->label('Quota mail')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('l')
                    ->label('Localité')
                    ->searchable()
                    ->options(fn (): array => Citoyen::query()
                        ->whereNotNull('l')
                        ->where('l', '!=', '')
                        ->distinct()
                        ->orderBy('l')
                        ->pluck('l', 'l')
                        ->all()),
                Filter::make('last_connection')
                    ->label('Dernière connexion')
                    ->schema([
                        DatePicker::make('connected_from')
                            ->label('Dernière connexion avant le'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['connected_from'],
                                fn (Builder $query, string $date): Builder => $query->whereDate(
                                    'last_connection',
                                    '<=',
                                    $date
                                )
                            );
                    }),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
