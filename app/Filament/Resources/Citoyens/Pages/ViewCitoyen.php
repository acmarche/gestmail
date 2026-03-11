<?php

declare(strict_types=1);

namespace App\Filament\Resources\Citoyens\Pages;

use App\Filament\Resources\Citoyens\CitoyenResource;
use App\Filament\Resources\Citoyens\Schemas\PasswordForm;
use App\Ldap\CitoyenHandler;
use App\Ldap\LdapCitoyenRepository;
use App\Repository\HandRepository;
use Exception;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Support\Enums\Size;
use Filament\Support\Icons\Heroicon;

final class ViewCitoyen extends ViewRecord
{
    protected static string $resource = CitoyenResource::class;

    public function getTitle(): string
    {
        return $this->record->mail ?? 'Empty name';
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->viewLdapAction(),
            ActionGroup::make([
                $this->quotaAction(),
                $this->passwordAction(),
                EditAction::make()
                    ->label('Modifier')
                    ->icon(Heroicon::Pencil),
                $this->handAction(),
            ])->label('Actions')
                ->button()
                ->size(Size::Large)
                ->color('secondary'),
        ];
    }

    private function viewLdapAction(): Action
    {
        return Action::make('viewLdap')
            ->label('Voir la fiche LDAP')
            ->icon(Heroicon::Eye)
            ->color('gray')
            ->modalHeading('Entrée LDAP')
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Fermer')
            ->schema(function (LdapCitoyenRepository $ldapCitoyenRepository): array {
                $ldapEntry = $ldapCitoyenRepository->getEntry($this->record->uid);

                if (!$ldapEntry) {
                    return [
                        TextEntry::make('error')
                            ->label('Erreur')
                            ->state('Entrée LDAP introuvable'),
                    ];
                }

                $attributes = [
                    'dn' => $ldapEntry->getDn(),
                    'uid' => $ldapEntry->getFirstAttribute('uid'),
                    'mail' => $ldapEntry->getFirstAttribute('mail'),
                    'cn' => $ldapEntry->getFirstAttribute('cn'),
                    'givenName' => $ldapEntry->getFirstAttribute('givenName'),
                    'sn' => $ldapEntry->getFirstAttribute('sn'),
                    'employeeNumber' => $ldapEntry->getFirstAttribute('employeeNumber'),
                    'postalAddress' => $ldapEntry->getFirstAttribute('postalAddress'),
                    'postalCode' => $ldapEntry->getFirstAttribute('postalCode'),
                    'l' => $ldapEntry->getFirstAttribute('l'),
                    'homeDirectory' => $ldapEntry->getFirstAttribute('homeDirectory'),
                    'gosaMailQuota' => $ldapEntry->getFirstAttribute('gosaMailQuota'),
                    'gosaMailForwardingAddress' => $ldapEntry->getFirstAttribute('gosaMailForwardingAddress'),
                    'gosaMailAlternateAddress' => $ldapEntry->getFirstAttribute('gosaMailAlternateAddress'),
                    'gosaMailServer' => $ldapEntry->getFirstAttribute('gosaMailServer'),
                    'gosaMailDeliveryMode' => $ldapEntry->getFirstAttribute('gosaMailDeliveryMode'),
                    'description' => $ldapEntry->getFirstAttribute('description'),
                ];

                $entries = [];
                foreach ($attributes as $key => $value) {
                    $displayValue = is_array($value) ? implode(', ', $value) : ($value ?? '-');
                    $entries[] = TextEntry::make($key)
                        ->label($key)
                        ->state($displayValue);
                }

                return [
                    Section::make('Attributs LDAP')
                        ->schema($entries)
                        ->columns(2),
                ];
            });
    }

    private function handAction(): Action
    {
        $handRepository = app(HandRepository::class);
        $existingHand = $handRepository->findByUid($this->record->uid);

        if ($existingHand) {
            return Action::make('hand')
                ->label('Restaurer le mot de passe')
                ->icon(Heroicon::ArrowUturnLeft)
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Restaurer le mot de passe original')
                ->modalDescription('Le mot de passe original du citoyen sera restauré.')
                ->action(function (LdapCitoyenRepository $ldapCitoyenRepository, HandRepository $handRepository): void {
                    try {
                        $ldapEntry = $ldapCitoyenRepository->getEntry($this->record->uid);

                        if (!$ldapEntry) {
                            throw new Exception('Utilisateur LDAP introuvable');
                        }

                        $existingHand = $handRepository->findByUid($this->record->uid);
                        $ldapCitoyenRepository->restorePassword($ldapEntry, $existingHand->password);
                        $handRepository->delete($existingHand);

                        Notification::make()
                            ->title('Mot de passe original restauré avec succès')
                            ->success()
                            ->send();
                    } catch (Exception $e) {
                        Notification::make()
                            ->title($e->getMessage())
                            ->danger()
                            ->send();
                    }
                });
        }

        return Action::make('hand')
            ->label('Prendre la main')
            ->icon(Heroicon::HandRaised)
            ->color('danger')
            ->schema([
                TextInput::make('password')
                    ->label('Mot de passe temporaire')
                    ->password()
                    ->required(),
            ])
            ->action(
                function (
                    array $data,
                    LdapCitoyenRepository $ldapCitoyenRepository,
                    HandRepository $handRepository
                ): void {
                    try {
                        $ldapEntry = $ldapCitoyenRepository->getEntry($this->record->uid);

                        if (!$ldapEntry) {
                            throw new Exception('Utilisateur LDAP introuvable');
                        }

                        $originalPassword = $ldapEntry->getFirstAttribute('userPassword');
                        $handRepository->create($this->record->uid, $this->record->mail, $originalPassword);
                        $ldapCitoyenRepository->changePassword($ldapEntry, $data['password']);

                        Notification::make()
                            ->title('Mot de passe temporaire défini avec succès')
                            ->success()
                            ->send();
                    } catch (Exception $e) {
                        Notification::make()
                            ->title($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }
            );
    }

    private function passwordAction(): Action
    {
        return Action::make('changePassword')
            ->label('Changer le mot de passe')
            ->icon(Heroicon::Key)
            ->color('warning')
            ->schema(PasswordForm::configure())
            ->action(function (array $data, CitoyenHandler $citoyenHandler): void {
                try {
                    $citoyenHandler->changePassword($this->record, $data['password']);

                    Notification::make()
                        ->title('Mot de passe modifié avec succès')
                        ->success()
                        ->send();
                } catch (Exception $e) {
                    Notification::make()
                        ->title($e->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }

    private function quotaAction(): Action
    {
        return Action::make('changeQuota')
            ->label('Changer le quota')
            ->icon(Heroicon::CircleStack)
            ->color('info')
            ->fillForm(fn(): array => [
                'gosaMailQuota' => $this->record->gosaMailQuota,
            ])
            ->schema(PasswordForm::quota())
            ->action(function (array $data, CitoyenHandler $citoyenHandler): void {
                try {
                    $citoyenHandler->changeQuota($this->record, (int)$data['gosaMailQuota']);

                    Notification::make()
                        ->title('Quota modifié avec succès')
                        ->success()
                        ->send();
                } catch (Exception $e) {
                    Notification::make()
                        ->title($e->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }
}
