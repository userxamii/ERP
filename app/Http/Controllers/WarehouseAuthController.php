<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;

class WarehouseAuthController extends Controller
{
    public function session(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'authenticated' => $user !== null,
            'hasAccounts' => User::query()->exists(),
            'csrfToken' => csrf_token(),
            'user' => $user?->only(['name', 'email', 'role']),
        ])->header('Cache-Control', 'no-store');
    }

    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'role' => ['required', Rule::in(User::ROLES)],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        User::query()->create($validated);

        return response()->json([
            'message' => 'Account created. Sign in with your new email and password.',
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $request->authenticate();
        $request->session()->regenerate();
        $user = $request->user();

        return response()->json([
            'user' => $user->only(['name', 'email', 'role']),
            'csrfToken' => csrf_token(),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json([
            'message' => 'Signed out.',
            'csrfToken' => csrf_token(),
        ]);
    }
}
