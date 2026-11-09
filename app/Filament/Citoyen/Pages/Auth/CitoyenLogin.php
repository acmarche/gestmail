<?php

declare(strict_types=1);

namespace App\Filament\Citoyen\Pages\Auth;

use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use Filament\Actions\Action;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Auth\Pages\Login;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Models\Contracts\FilamentUser;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Auth\SessionGuard;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;

final class CitoyenLogin extends Login
{
    public function authenticate(): ?LoginResponse
    {
        try {
            $this->rateLimit(5);
        } catch (TooManyRequestsException $exception) {
            $this->getRateLimitedNotification($exception)?->send();

            return null;
        }

        $data = $this->form->getState();

        /** @var SessionGuard $authGuard */
        $authGuard = Filament::auth();

        $credentials = $this->getCredentialsFromFormData($data);

        if (! $authGuard->attemptWhen($credentials, function (Authenticatable $user): bool {
            if (! ($user instanceof FilamentUser)) {
                return true;
            }

            return $user->canAccessPanel(Filament::getCurrentOrDefaultPanel());
        })) {
            $this->throwFailureValidationException();
        }

        session()->regenerate();

        return app(LoginResponse::class);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getMethodFormComponent(),
                $this->getUsernameFormComponent(),
                $this->getPasswordFormComponent(),
                $this->getTokenFormComponent(),
            ]);
    }

    public function getHeading(): string|Htmlable|null
    {
        return 'Espace Citoyen';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return new HtmlString('Connectez-vous avec votre mot de passe ou votre jeton personnel. '.$this->recoveryAction->toHtml());
    }

    public function recoveryAction(): Action
    {
        return Action::make('recovery')
            ->link()
            ->label('Mot de passe oublié ?')
            ->url(route('filament.citoyen.auth.recovery'));
    }

    protected function getMethodFormComponent(): Component
    {
        return ToggleButtons::make('method')
            ->hiddenLabel()
            ->options([
                'password' => 'Mot de passe',
                'token' => 'Jeton personnel',
            ])
            ->default('password')
            ->inline()
            ->required()
            ->live();
    }

    protected function getUsernameFormComponent(): Component
    {
        return TextInput::make('username')
            ->label('Adresse e-mail')
            ->required()
            ->autocomplete('username')
            ->autofocus()
            ->visible(fn (Get $get): bool => $get('method') === 'password');
    }

    protected function getPasswordFormComponent(): Component
    {
        return TextInput::make('password')
            ->label('Mot de passe')
            ->password()
            ->revealable()
            ->required()
            ->autocomplete('current-password')
            ->visible(fn (Get $get): bool => $get('method') === 'password');
    }

    protected function getTokenFormComponent(): Component
    {
        return TextInput::make('token')
            ->label('Jeton personnel')
            ->password()
            ->revealable()
            ->required()
            ->autocomplete('off')
            ->visible(fn (Get $get): bool => $get('method') === 'token');
    }

    protected function getCredentialsFromFormData(array $data): array
    {
        if ($data['method'] === 'token') {
            return ['token' => $data['token']];
        }

        return [
            'username' => $data['username'],
            'password' => $data['password'],
        ];
    }

    protected function throwFailureValidationException(): never
    {
        $field = ($this->data['method'] ?? null) === 'token' ? 'data.token' : 'data.username';

        throw ValidationException::withMessages([
            $field => __('filament-panels::auth/pages/login.messages.failed'),
        ]);
    }
}
