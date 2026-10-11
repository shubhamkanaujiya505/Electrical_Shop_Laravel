<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureShopAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_unless($user && $user->is_active && in_array($user->role, ['owner', 'staff'], true) && $user->tenant?->is_active, 403, 'Shop access is unavailable.');

        return $next($request);
    }
}
