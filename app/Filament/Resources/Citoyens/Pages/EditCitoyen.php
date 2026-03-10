<?php

declare(strict_types=1);

namespace App\Filament\Resources\Citoyens\Pages;

use App\Filament\Resources\Citoyens\CitoyenResource;
use App\Ldap\CitoyenHandler;
use App\Ldap\CitoyenLdap;
use App\Ldap\LdapCitoyenRepository;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

final class EditCitoyen extends EditRecord
{
    protected static string $resource = CitoyenResource::class;

    public function getTitle(): string
    {
        return $this->record->mail ?? 'Empty name';
    }

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make()
                ->icon(Heroicon::Eye),
        ];
    }

    protected function afterSave(): void
    {
        $citoyenHandler = app(CitoyenHandler::class);
        $ldapCitoyenRepository = app(LdapCitoyenRepository::class);
        $citoyen = $this->record;

        try {
            $ldapEntry = $ldapCitoyenRepository->getEntryByEmail($citoyen->mail);

            if (!$ldapEntry) {
                Notification::make()
                    ->title('Utilisateur LDAP introuvable pour '.$citoyen->mail)
                    ->warning()
                    ->send();

                return;
            }

            $citoyenHandler->updateCitoyen($citoyen, $ldapEntry);

            Notification::make()
                ->title('Entrée LDAP mise à jour')
                ->success()
                ->send();
        } catch (\Exception $e) {
            Notification::make()
                ->title('Erreur LDAP: '.$e->getMessage())
                ->danger()
                ->send();
        }
    }
}
