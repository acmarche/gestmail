<?php

declare(strict_types=1);

namespace App\Filament\Citoyen\Pages;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

final class RecoveryInfo extends Page implements HasForms
{
    use InteractsWithForms;

    public ?array $data = [];

    protected string $view = 'filament.citoyen.pages.recovery-info';

    protected static ?string $title = 'Informations de secours';

    protected static ?string $navigationLabel = 'Informations de secours';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    public function mount(): void
    {
        $user = Filament::auth()->user();

        $this->form->fill([
            'recovery_email' => $user->recovery_email,
            'recovery_phone' => $user->recovery_phone,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('recovery_email')
                    ->label('Email de secours')
                    ->email()
                    ->helperText('Adresse email alternative pour récupérer votre mot de passe.'),
                TextInput::make('recovery_phone')
                    ->label('Téléphone mobile')
                    ->tel()
                    ->helperText('Numéro de téléphone pour récupérer votre mot de passe.'),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        Filament::auth()->user()->update([
            'recovery_email' => $data['recovery_email'],
            'recovery_phone' => $data['recovery_phone'],
        ]);

        Notification::make()
            ->title('Informations de secours mises à jour')
            ->success()
            ->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->label('Enregistrer')
                ->icon(Heroicon::Check)
                ->action('save'),
        ];
    }
}
