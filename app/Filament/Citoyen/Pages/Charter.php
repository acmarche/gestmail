<?php

declare(strict_types=1);

namespace App\Filament\Citoyen\Pages;

use Filament\Facades\Filament;
use Filament\Pages\Page;

final class Charter extends Page
{
    protected string $view = 'filament.citoyen.pages.charter';

    protected static ?string $slug = 'charter';

    protected static ?string $title = 'Charte d\'utilisation';

    protected static bool $shouldRegisterNavigation = false;

    public function accept(): void
    {
        $user = Filament::auth()->user();
        $user->update(['charter_accepted_at' => now()]);

        $this->redirect(Filament::getUrl());
    }
}
