<?php

namespace App\Filament\Resources\Citoyens\Pages;

use App\Filament\Resources\Citoyens\CitoyenResource;
use App\Filament\Resources\Citoyens\Tables\CitoyensTable;
use App\Ldap\LdapCitoyenRepository;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Table;

class ListCitoyens extends ListRecords
{
    protected static string $resource = CitoyenResource::class;

    public function mount(): void
    {
        $ldapCitoyenRepository = app(LdapCitoyenRepository::class);
    }

    public function table(Table $table): Table
    {
      $citizens=   $this->ldapCitoyenRepository->getAll();

        return CitoyensTable::configure($table)
            ->records(fn(): array => [
            1 => [
                'last_name' => 'First item',
                'first_name' => 'first-item',
                'email' => true,
            ],
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
