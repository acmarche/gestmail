<?php

declare(strict_types=1);

use App\Models\Citoyen;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// Route::view('/', 'welcome');
Route::get('/', fn () => redirect('/admin'));

Route::get('/citoyen/login/{token}', function (string $token) {
    $citoyen = Citoyen::where('auth_token', $token)->first();

    if (! $citoyen) {
        abort(403);
    }

    Auth::guard('citoyen')->login($citoyen);
    session()->regenerate();

    return redirect('/citoyen');
})->name('citoyen.auto-login')->middleware(['web']);
