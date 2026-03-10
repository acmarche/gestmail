<?php

declare(strict_types=1);

namespace App\Filament\Resources\Citoyens\Pages;

use App\Filament\Resources\Citoyens\CitoyenResource;
use App\Filament\Resources\Citoyens\Tables\CitoyensTable;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;

final class ListCitoyens extends ListRecords
{
    protected static string $resource = CitoyenResource::class;

    protected static ?string $navigationLabel = 'citoyens';

    public function getTitle(): string|Htmlable
    {
        return $this->getAllTableRecordsCount().' citoyens';
    }

    public function table(Table $table): Table
    {
        return CitoyensTable::configure($table);
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Ajouter un citoyen')
                ->icon(Heroicon::Plus),
        ];
    }
}
