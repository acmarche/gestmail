<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureCharterAccepted
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Filament::auth()->user();

        if (! $user || $user->charter_accepted_at) {
            return $next($request);
        }

        if (
            $request->routeIs('filament.citoyen.pages.charter') ||
            $request->routeIs('filament.citoyen.auth.logout')
        ) {
            return $next($request);
        }

        return redirect()->route('filament.citoyen.pages.charter');
    }
}
