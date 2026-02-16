<?php

declare(strict_types=1);

namespace App\Filament\Resources\Citoyens\Pages;

use App\Filament\Resources\Citoyens\CitoyenResource;
use App\Filament\Resources\Citoyens\Tables\CitoyensTable;
use App\Ldap\LdapCitoyenRepository;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Table;

final class ListCitoyens extends ListRecords
{
    protected static string $resource = CitoyenResource::class;

    public function table(Table $table): Table
    {
        return CitoyensTable::configure($table)
            ->records(function (): array {
                $repository = app(LdapCitoyenRepository::class);
                $citizens = $repository->getAll();

                $records = [];
                foreach ($citizens as $citizen) {
                    $uid = $citizen->getFirstAttribute('uid');
                    $records[$uid] = [
                        'last_name' => $citizen->getFirstAttribute('sn'),
                        'first_name' => $citizen->getFirstAttribute('givenName'),
                        'email' => $citizen->getFirstAttribute('mail'),
                        'l' => $citizen->getFirstAttribute('l'),
                        'employeNumber' => $citizen->getFirstAttribute('employeNumber'),
                        'gosaMailQuota' => $citizen->getFirstAttribute('gosaMailQuota'),
                    ];
                }

                return $records;
            })
            ->recordAction(fn (array $record): ?string => null)
            ->recordUrl(fn (array $record): ?string => null);
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
