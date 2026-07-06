<?php

declare(strict_types=1);

namespace App\Filament\Citoyen\Pages;

use App\Ldap\CitoyenHandler;
use Exception;
use Filament\Facades\Filament;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ViewField;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\Rules\Password;

final class Onboarding extends Page implements HasForms
{
    use InteractsWithForms;

    public ?array $data = [];

    protected string $view = 'filament.citoyen.pages.onboarding';

    protected static ?string $slug = 'onboarding';

    protected static ?string $title = 'Configuration de votre compte';

    protected static bool $shouldRegisterNavigation = false;

    public function mount(): void
    {
        $user = Filament::auth()->user();

        if ($user->hasCompletedOnboarding()) {
            $this->redirect(Filament::getUrl());

            return;
        }

        $this->form->fill([
            'recovery_email' => $user->recovery_email,
            'recovery_phone' => $user->recovery_phone,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Wizard::make([
                    Step::make('Charte d\'utilisation')
                        ->icon('heroicon-o-document-text')
                        ->schema([
                            ViewField::make('charter_content')
                                ->view('filament.citoyen.pages.charter-content')
                                ->hiddenLabel(),
                            Checkbox::make('accept_charter')
                                ->label('J\'accepte la charte d\'utilisation')
                                ->accepted()
                                ->validationMessages([
                                    'accepted' => 'Vous devez accepter la charte pour continuer.',
                                ]),
                        ]),
                    Step::make('Mot de passe')
                        ->icon('heroicon-o-key')
                        ->description('Définissez un nouveau mot de passe pour votre messagerie')
                        ->schema([
                            TextInput::make('new_password')
                                ->label('Nouveau mot de passe')
                                ->password()
                                ->revealable()
                                ->helperText('min 12 caractères, minuscule, majuscule et nombre')
                                ->rule(Password::defaults())
                                ->required()
                                ->confirmed(),
                            TextInput::make('new_password_confirmation')
                                ->label('Confirmer le mot de passe')
                                ->password()
                                ->revealable()
                                ->required(),
                        ]),
                    Step::make('Informations de secours')
                        ->icon('heroicon-o-shield-check')
                        ->description('Renseignez au moins un moyen de récupération')
                        ->schema([
                            TextInput::make('recovery_email')
                                ->label('Email de secours')
                                ->email()
                                ->helperText('Adresse email alternative pour récupérer votre mot de passe.')
                                ->requiredWithout('data.recovery_phone'),
                            TextInput::make('recovery_phone')
                                ->label('Téléphone mobile')
                                ->tel()
                                ->helperText('Numéro de téléphone pour récupérer votre mot de passe.')
                                ->requiredWithout('data.recovery_email'),
                        ]),
                ])
                    ->submitAction(new HtmlString(Blade::render(<<<'BLADE'
                        <x-filament::button type="submit" size="sm">
                            Terminer la configuration
                        </x-filament::button>
                    BLADE))),
            ])
            ->statePath('data');
    }

    public function submit(CitoyenHandler $citoyenHandler): void
    {
        $data = $this->form->getState();

        $user = Filament::auth()->user();

        try {
            $citoyenHandler->changePassword($user, $data['new_password']);

            $user->update([
                'charter_accepted_at' => now(),
                'password_changed_at' => now(),
                'recovery_email' => $data['recovery_email'],
                'recovery_phone' => $data['recovery_phone'],
            ]);

            Notification::make()
                ->title('Votre compte est configuré avec succès')
                ->success()
                ->send();

            $this->redirect(Filament::getUrl());
        } catch (Exception $e) {
            Notification::make()
                ->title('Erreur lors de la configuration')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
}
