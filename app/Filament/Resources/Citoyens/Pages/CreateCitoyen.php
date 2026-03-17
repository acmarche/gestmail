<?php

declare(strict_types=1);

namespace App\Filament\Resources\Citoyens\Pages;

use App\Filament\Resources\Citoyens\CitoyenResource;
use App\Filament\Resources\Citoyens\Schemas\CitoyenForm;
use App\Ldap\CitoyenHandler;
use App\Models\Citoyen;
use Exception;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;
use LdapRecord\LdapRecordException;

final class CreateCitoyen extends CreateRecord
{
    protected static string $resource = CitoyenResource::class;

    public function form(Schema $schema): Schema
    {
        return CitoyenForm::forCreating($schema);
    }

    protected function handleRecordCreation(array $data): Citoyen
    {
        $citoyenHandler = app(CitoyenHandler::class);

        try {
            $citoyen = $citoyenHandler->createCitoyen($data);
        } catch (Exception|LdapRecordException $exception) {
            $error = $exception->getMessage();
            if ($exception instanceof LdapRecordException && $exception->getDetailedError()) {
                $error .= ' '.$exception->getDetailedError()->getDiagnosticMessage();
            }

            Notification::make()
                ->title('Erreur lors de la création')
                ->body($error)
                ->danger()
                ->send();

            $this->halt();
        }

        $body = Str::markdown(
            view('filament.citoyens.created-notification', [
                'mail' => $data['mail'],
                'uid' => $citoyen->uid,
                'password' => $data['password'],
            ])->render(),
        );

        Notification::make()
            ->title('Le citoyen a bien été ajouté.')
            ->body($body)
            ->success()
            ->persistent()
            ->send();

        return $citoyen;
    }

    protected function getCreatedNotification(): ?Notification
    {
        return null;
    }
}
