<?php

declare(strict_types=1);

namespace App\Filament\Resources\Citoyens\Pages;

use App\Filament\Resources\Citoyens\CitoyenResource;
use App\Filament\Resources\Citoyens\Tables\CitoyensTable;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Table;

final class ListCitoyens extends ListRecords
{
    protected static string $resource = CitoyenResource::class;

    public function table(Table $table): Table
    {
        return CitoyensTable::configure($table);
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
