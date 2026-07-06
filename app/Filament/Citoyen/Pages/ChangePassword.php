<?php

declare(strict_types=1);

namespace App\Filament\Citoyen\Pages;

use App\Ldap\CitoyenHandler;
use BackedEnum;
use Exception;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Validation\Rules\Password;

final class ChangePassword extends Page implements HasForms
{
    use InteractsWithForms;

    public ?array $data = [];

    protected string $view = 'filament.citoyen.pages.change-password';

    protected static ?string $title = 'Changer le mot de passe';

    protected static ?string $navigationLabel = 'Mot de passe';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('new_password')
                    ->label('Nouveau mot de passe')
                    ->password()
                    ->revealable()
                    ->helperText('min 12 caractères, minuscule, majuscule et nombre')
                    ->rule(Password::min(12)->letters()->mixedCase()->numbers())
                    ->required()
                    ->confirmed(),
                TextInput::make('new_password_confirmation')
                    ->label('Confirmer le mot de passe')
                    ->password()
                    ->revealable()
                    ->required(),
            ])
            ->statePath('data');
    }

    public function save(CitoyenHandler $citoyenHandler): void
    {
        $data = $this->form->getState();

        try {
            $user = Filament::auth()->user();

            $citoyenHandler->changePassword($user, $data['new_password']);

            $this->form->fill();

            Notification::make()
                ->title('Mot de passe modifié avec succès')
                ->success()
                ->send();
        } catch (Exception $e) {
            Notification::make()
                ->title('Erreur lors du changement de mot de passe')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
}
