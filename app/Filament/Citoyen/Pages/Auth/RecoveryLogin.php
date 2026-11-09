<?php

declare(strict_types=1);

namespace App\Filament\Citoyen\Pages\Auth;

use App\Models\Citoyen;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\SimplePage;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;

/**
 * Opened from the link sent by {@see RequestRecoveryLink}. The token is single-use: it is only
 * consumed by the "Continuer" button, never by opening the page, so a mail scanner that
 * prefetches the link does not burn it. The cache only holds a hash of the token. The
 * citoyen is logged in through its mailbox login, like on the login page.
 */
final class RecoveryLogin extends SimplePage
{
    public const int LIFETIME_MINUTES = 30;

    #[Locked]
    public string $token = '';

    public bool $isValid = false;

    /**
     * Creates the token of a new link and cancels the previous link of the citoyen.
     */
    public static function issueToken(Citoyen $citoyen): string
    {
        $token = Str::random(64);
        $expiresAt = now()->addMinutes(self::LIFETIME_MINUTES);

        $previous = Cache::get(self::latestKey($citoyen));
        if ($previous !== null) {
            Cache::forget($previous);
        }

        Cache::put(self::tokenKey($token), $citoyen->id, $expiresAt);
        Cache::put(self::latestKey($citoyen), self::tokenKey($token), $expiresAt);

        return $token;
    }

    public function mount(string $token): void
    {
        $this->token = $token;
        $this->isValid = Cache::has(self::tokenKey($token));
    }

    public function content(Schema $schema): Schema
    {
        if (! $this->isValid) {
            return $schema->components([
                Text::make("Ce lien n'est plus valable : il a déjà été utilisé, il a expiré ou un lien plus récent a été envoyé."),
                Actions::make([
                    Action::make('requestAgain')
                        ->label('Demander un nouveau lien')
                        ->url(route('filament.citoyen.auth.recovery')),
                ]),
            ]);
        }

        return $schema->components([
            Text::make('Ce lien ne fonctionne qu\'une seule fois. Continuez pour choisir un nouveau mot de passe.'),
            Actions::make([
                Action::make('continue')
                    ->label('Continuer')
                    ->action(fn () => $this->continue()),
            ])->key('recovery-actions')->fullWidth(),
        ]);
    }

    public function getTitle(): string|Htmlable
    {
        return 'Récupération du mot de passe';
    }

    public function getHeading(): string|Htmlable|null
    {
        return 'Récupération du mot de passe';
    }

    private static function tokenKey(string $token): string
    {
        return 'citoyen-recovery:'.hash('sha256', $token);
    }

    private static function latestKey(Citoyen $citoyen): string
    {
        return 'citoyen-recovery-latest:'.$citoyen->id;
    }

    private function continue(): void
    {
        $citoyen = Citoyen::find(Cache::pull(self::tokenKey($this->token)));
        $virtualUser = $citoyen?->virtualUser;

        if ($virtualUser === null || ! $virtualUser->canAccessPanel(Filament::getCurrentOrDefaultPanel())) {
            $this->isValid = false;

            Notification::make()
                ->title("Ce lien n'est plus valable")
                ->danger()
                ->send();

            return;
        }

        Cache::forget(self::latestKey($citoyen));

        Filament::auth()->login($virtualUser);
        session()->regenerate();

        $this->redirectRoute('filament.citoyen.pages.change-password');
    }
}
