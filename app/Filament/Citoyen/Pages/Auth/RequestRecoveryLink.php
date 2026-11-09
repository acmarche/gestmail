<?php

declare(strict_types=1);

namespace App\Filament\Citoyen\Pages\Auth;

use App\Models\Citoyen;
use App\Notifications\CitoyenRecoveryLink;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use Filament\Auth\Pages\PasswordReset\RequestPasswordReset;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Component;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Notification as NotificationFacade;

/**
 * The citoyen gives the address of their mailbox, never the recovery email: the recovery
 * columns are encrypted and cannot be searched. The link goes to the recovery email stored
 * on that mailbox. The answer is the same whether the mailbox exists or not.
 */
final class RequestRecoveryLink extends RequestPasswordReset
{
    public function request(): void
    {
        try {
            $this->rateLimit(2);
        } catch (TooManyRequestsException $exception) {
            $this->getRateLimitedNotification($exception)?->send();

            return;
        }

        $data = $this->form->getState();

        $citoyen = Citoyen::query()->where('mail', mb_strtolower(mb_trim($data['mail'])))->first();

        if ($citoyen && filled($citoyen->recovery_email)) {
            $url = route('filament.citoyen.auth.recovery-login', ['token' => RecoveryLogin::issueToken($citoyen)]);

            NotificationFacade::route('mail', $citoyen->recovery_email)
                ->notify(new CitoyenRecoveryLink($citoyen->mail, $url, RecoveryLogin::LIFETIME_MINUTES));
        }

        Notification::make()
            ->title('Demande enregistrée')
            ->body("Si cette adresse a un email de secours, un lien pour choisir un nouveau mot de passe vient d'y être envoyé.")
            ->success()
            ->send();

        $this->form->fill();
    }

    public function getTitle(): string|Htmlable
    {
        return 'Mot de passe oublié';
    }

    public function getHeading(): string|Htmlable|null
    {
        return 'Mot de passe oublié ?';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Nous enverrons un lien à votre email de secours.';
    }

    protected function getEmailFormComponent(): Component
    {
        return TextInput::make('mail')
            ->label('Adresse de votre boîte mail')
            ->placeholder('prenom.nom@marche.be')
            ->email()
            ->required()
            ->autocomplete('email')
            ->autofocus();
    }
}
