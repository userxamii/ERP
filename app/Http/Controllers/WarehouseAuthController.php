<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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
            'workspaceState' => $user instanceof User ? $this->workspaceState($user) : [
                'productStatuses' => [],
                'readAlertIds' => [],
            ],
        ])->header('Cache-Control', 'no-store');
    }

    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'role' => ['required', Rule::in(array_diff(User::ROLES, ['System Administrator']))],
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
        abort_unless($user instanceof User, 401);

        return response()->json([
            'user' => $user->only(['name', 'email', 'role']),
            'csrfToken' => csrf_token(),
            'workspaceState' => $this->workspaceState($user),
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

    /**
     * @return array{productStatuses: array<string, string>, readAlertIds: list<string>}
     */
    private function workspaceState(User $user): array
    {
        return [
            'productStatuses' => DB::table('product_monitor_statuses')->pluck('status', 'product_id')->all(),
            'readAlertIds' => DB::table('product_monitor_alert_reads')
                ->where('user_id', $user->id)
                ->pluck('alert_id')
                ->all(),
        ];
    }
}
