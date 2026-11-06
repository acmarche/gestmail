<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;

it('protège le panneau citoyen contre la falsification de requêtes', function (): void {
    expect(Filament::getPanel('citoyen')->getMiddleware())
        ->toContain(PreventRequestForgery::class);
});
