<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use App\Filament\Citoyen\Pages\Auth\CitoyenLogin;
use App\Filament\Citoyen\Pages\Auth\RecoveryLogin;
use App\Filament\Citoyen\Pages\Auth\RequestRecoveryLink;
use App\Http\Middleware\EnsureCharterAccepted;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

final class CitoyenPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('citoyen')
            ->path('citoyen')
            ->authGuard('virtual_user')
            ->login(CitoyenLogin::class)
            ->routes(function (): void {
                Route::get('mot-de-passe-oublie', RequestRecoveryLink::class)->name('auth.recovery');
                Route::get('recuperation/{token}', RecoveryLogin::class)->name('auth.recovery-login');
            })
            ->spa()
            ->colors([
                'primary' => Color::Emerald,
            ])
            ->discoverPages(in: app_path('Filament/Citoyen/Pages'), for: 'App\Filament\Citoyen\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Citoyen/Widgets'), for: 'App\Filament\Citoyen\Widgets')
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
                EnsureCharterAccepted::class,
            ]);
    }
}
