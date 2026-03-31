<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use App\Filament\Citoyen\Pages\Auth\CitoyenLogin;
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
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

final class CitoyenPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('citoyen')
            ->path('citoyen')
            ->authGuard('citoyen')
            ->login(CitoyenLogin::class)
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
                VerifyCsrfToken::class,
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
