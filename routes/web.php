<?php

declare(strict_types=1);

use App\Models\VirtualUser;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// Route::view('/', 'welcome');
Route::get('/', fn () => redirect('/citoyen'));

Route::get('/citoyen/login/{token}', function (string $token) {
    $isLoggedIn = Auth::guard('virtual_user')->attemptWhen(
        ['token' => $token],
        fn (VirtualUser $virtualUser): bool => $virtualUser->canAccessPanel(Filament::getPanel('citoyen')),
    );

    abort_unless($isLoggedIn, 403);

    session()->regenerate();

    return redirect('/citoyen');
})->name('citoyen.auto-login')->middleware(['web']);
