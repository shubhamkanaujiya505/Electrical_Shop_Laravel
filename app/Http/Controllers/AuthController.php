<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = DB::transaction(function () use ($request) {
            $tenant = Tenant::create([
                'name' => $request->validated('shop_name'),
                'phone' => $request->validated('phone'),
            ]);

            return $tenant->users()->create([
                'name' => $request->validated('name'),
                'email' => $request->validated('email'),
                'password' => $request->validated('password'),
                'role' => 'owner',
            ]);
        });

        Auth::login($user);
        $request->session()->regenerate();

        return $this->sessionResponse($request, 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $key = 'account-login:'.$request->validated('email').'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return response()->json(['message' => 'Too many login attempts. Please try again shortly.'], 429)
                ->header('Retry-After', (string) RateLimiter::availableIn($key));
        }

        if (! Auth::attempt($request->safe()->only(['email', 'password']))) {
            RateLimiter::hit($key, 60);
            throw ValidationException::withMessages(['email' => 'The email or password is incorrect.']);
        }

        $user = $request->user();
        if (! $user->is_active || ! in_array($user->role, ['owner', 'staff'], true) || ! $user->tenant?->is_active) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            RateLimiter::hit($key, 60);
            throw ValidationException::withMessages(['email' => 'The email or password is incorrect.']);
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();

        return $this->sessionResponse($request);
    }

    public function me(Request $request): JsonResponse
    {
        return $this->sessionResponse($request);
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Signed out successfully.']);
    }

    private function sessionResponse(Request $request, int $status = 200): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'user' => $user->only(['id', 'name', 'email', 'role']),
            'shop' => $user->tenant->only(['id', 'name', 'phone', 'address']),
            'permissions' => array_values(array_filter(['shop.update', 'staff.manage'], fn ($permission) => $user->hasShopPermission($permission))),
        ], $status);
    }
}
