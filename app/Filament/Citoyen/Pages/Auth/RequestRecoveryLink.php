<?php

declare(strict_types=1);

namespace App\Filament\Citoyen\Pages\Auth;

use App\Models\Citoyen;
use App\Notifications\CitoyenRecoveryLink;
use App\Sms\Exception\SmsException;
use App\Sms\InforiusClient;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use Filament\Auth\Pages\PasswordReset\RequestPasswordReset;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Component;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Throwable;

/**
 * The citoyen gives the address of their mailbox, never the recovery email or phone: the
 * recovery columns are encrypted and cannot be searched. The link goes to the recovery email
 * and the recovery phone stored on that mailbox. The answer is the same whether the mailbox
 * exists or not.
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

        if ($citoyen && (filled($citoyen->recovery_email) || filled($citoyen->recovery_phone))) {
            $this->sendRecoveryLink($citoyen);
        }

        Notification::make()
            ->title('Demande enregistrée')
            ->body("Si cette adresse a un email ou un téléphone de secours, un lien pour choisir un nouveau mot de passe vient d'y être envoyé.")
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
        return 'Nous enverrons un lien à votre email ou à votre téléphone de secours.';
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

    /**
     * One link, sent to every recovery channel of the citoyen: issuing a second token would
     * cancel the first. A failing SMS gateway is reported but never shown, so the answer
     * stays the same for every address.
     */
    private function sendRecoveryLink(Citoyen $citoyen): void
    {
        $url = route('filament.citoyen.auth.recovery-login', ['token' => RecoveryLogin::issueToken($citoyen)]);

        if (filled($citoyen->recovery_email)) {
            NotificationFacade::route('mail', $citoyen->recovery_email)
                ->notify(new CitoyenRecoveryLink($citoyen->mail, $url, RecoveryLogin::LIFETIME_MINUTES));
        }

        if (filled($citoyen->recovery_phone)) {
            try {
                $response = app(InforiusClient::class)->sendSms(
                    number: $citoyen->recovery_phone,
                    message: sprintf(
                        'Ville de Marche : lien pour changer le mot de passe de %s, valable %d min et une seule fois : %s',
                        $citoyen->mail,
                        RecoveryLogin::LIFETIME_MINUTES,
                        $url,
                    ),
                    customerReference: 'recovery-'.$citoyen->id,
                );

                if (! $response->isSuccessful()) {
                    throw new SmsException($response->error ?? $response->messages[0]->errorMessage ?? 'Erreur inconnue');
                }
            } catch (Throwable $exception) {
                report($exception);
            }
        }
    }
}
