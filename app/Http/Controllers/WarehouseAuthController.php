<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;

class WarehouseAuthController extends Controller
{
    public function session(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'authenticated' => $user !== null,
            'csrfToken' => csrf_token(),
            'user' => $user?->only(['name', 'email', 'role']),
            'mustChangePassword' => $user instanceof User && $user->must_change_password,
            'workspaceState' => $user instanceof User && ! $user->must_change_password ? $this->workspaceState($user) : [
                'productStatuses' => [],
                'readAlertIds' => [],
            ],
        ])->header('Cache-Control', 'no-store');
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $request->authenticate();
        $request->session()->regenerate();
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return response()->json([
            'user' => $user->only(['name', 'email', 'role']),
            'mustChangePassword' => $user->must_change_password,
            'csrfToken' => csrf_token(),
            'workspaceState' => $user->must_change_password
                ? ['productStatuses' => [], 'readAlertIds' => []]
                : $this->workspaceState($user),
        ]);
    }

    public function changePassword(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        abort_unless($user->must_change_password, 409, 'A password change is not required for this account.');

        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Rules\Password::defaults(), 'different:current_password'],
        ]);

        $user->forceFill([
            'password' => Hash::make($validated['password']),
            'must_change_password' => false,
            'remember_token' => Str::random(60),
        ])->save();
        $request->session()->regenerate();

        return response()->json([
            'message' => 'Your password has been changed.',
            'mustChangePassword' => false,
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
