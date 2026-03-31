<?php

declare(strict_types=1);

namespace App\Filament\Citoyen\Pages;

use Filament\Pages\Page;

final class Charter extends Page
{
    protected string $view = 'filament.citoyen.pages.charter';

    protected static ?string $slug = 'charter';

    protected static ?string $title = 'Charte d\'utilisation';

    protected static bool $shouldRegisterNavigation = false;
}
