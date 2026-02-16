<?php

namespace App\Filament\Resources\Citoyens;

use App\Filament\Resources\Citoyens\Pages\CreateCitoyen;
use App\Filament\Resources\Citoyens\Pages\EditCitoyen;
use App\Filament\Resources\Citoyens\Pages\ListCitoyens;
use App\Filament\Resources\Citoyens\Schemas\CitoyenForm;
use App\Filament\Resources\Citoyens\Tables\CitoyensTable;
use App\Models\Citoyen;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class CitoyenResource extends Resource
{
    protected static ?string $model = Citoyen::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return CitoyenForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CitoyensTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCitoyens::route('/'),
            'create' => CreateCitoyen::route('/create'),
            'edit' => EditCitoyen::route('/{record}/edit'),
        ];
    }
}
