<?php

namespace App\Http\Controllers;

use App\Models\SystemActivity;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SystemAdministratorController extends Controller
{
    public function dashboard(Request $request): JsonResponse
    {
        $this->authorizeAdministrator($request);

        try {
            DB::connection()->select('select 1');
            $databaseStatus = 'Operational';
        } catch (QueryException $exception) {
            report($exception);
            $databaseStatus = 'Unavailable';
        }

        $recentActivities = SystemActivity::query()
            ->with(['user:id,name', 'actor:id,name'])
            ->latest()
            ->orderByDesc('id')
            ->limit(10)
            ->get(['id', 'user_id', 'actor_user_id', 'event_type', 'description', 'ip_address', 'metadata', 'created_at']);

        return response()->json([
            'metrics' => [
                'registeredUsers' => User::query()->count(),
                'activeUsers' => User::query()->where('is_active', true)->count(),
                'successfulLogins' => SystemActivity::query()
                    ->where('event_type', 'login_succeeded')
                    ->where('created_at', '>=', now()->subDay())
                    ->count(),
                'failedLogins' => SystemActivity::query()
                    ->whereIn('event_type', ['login_failed', 'login_locked'])
                    ->where('created_at', '>=', now()->subDay())
                    ->count(),
            ],
            'health' => [
                'api' => 'Operational',
                'database' => $databaseStatus,
            ],
            'recentActivity' => $recentActivities->map(fn (SystemActivity $activity): array => [
                'id' => $activity->id,
                'eventType' => $activity->event_type,
                'description' => $activity->description,
                'user' => $activity->user?->name,
                'actor' => $activity->actor?->name,
                'email' => $activity->metadata['email'] ?? $activity->user?->email,
                'ipAddress' => $activity->ip_address,
                'createdAt' => $activity->created_at?->toIso8601String(),
            ]),
        ])->header('Cache-Control', 'no-store');
    }

    public function users(Request $request): JsonResponse
    {
        $this->authorizeAdministrator($request);

        return response()->json([
            'users' => User::query()
                ->orderBy('name')
                ->get(['id', 'name', 'email', 'role', 'department', 'is_active', 'created_at'])
                ->map(fn (User $user): array => $this->userData($user)),
        ])->header('Cache-Control', 'no-store');
    }

    public function storeUser(Request $request): JsonResponse
    {
        $actor = $this->authorizeAdministrator($request);
        $validated = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', Rule::in(User::ROLES)],
            'department' => ['nullable', 'string', 'max:100'],
        ]);

        $user = User::query()->create([
            ...$validated,
            'password' => Str::random(64),
            'is_active' => true,
        ]);

        $resetStatus = Password::sendResetLink(['email' => $user->email]);
        $this->recordActivity($actor, $user, 'user_created', "Created account for {$user->name}.");

        return response()->json([
            'user' => $this->userData($user),
            'message' => $resetStatus === Password::RESET_LINK_SENT
                ? 'Account created. A password setup link has been sent by email.'
                : 'Account created, but a password setup email could not be sent.',
            'resetLinkSent' => $resetStatus === Password::RESET_LINK_SENT,
        ], 201);
    }

    public function updateUser(Request $request, User $user): JsonResponse
    {
        $actor = $this->authorizeAdministrator($request);
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'min:2', 'max:255'],
            'email' => ['sometimes', 'required', 'string', 'lowercase', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'role' => ['sometimes', 'required', Rule::in(User::ROLES)],
            'department' => ['sometimes', 'nullable', 'string', 'max:100'],
            'is_active' => ['sometimes', 'required', 'boolean'],
        ]);

        if ($user->is($actor) && (
            (isset($validated['role']) && $validated['role'] !== 'System Administrator')
            || (array_key_exists('is_active', $validated) && ! $validated['is_active'])
        )) {
            abort(422, 'You cannot remove your own administrator access or deactivate your account.');
        }

        $newStatus = filter_var($validated['is_active'] ?? $user->is_active, FILTER_VALIDATE_BOOLEAN);

        DB::transaction(function () use ($actor, $newStatus, $user, $validated): void {
            $this->ensureAdministratorRemains(
                $user,
                $validated['role'] ?? $user->role,
                $newStatus,
            );

            $user->fill($validated);
            $user->save();

            $this->recordActivity($actor, $user, 'user_updated', "Updated account for {$user->name}.");
        });

        return response()->json([
            'user' => $this->userData($user->refresh()),
        ]);
    }

    public function sendPasswordReset(Request $request, User $user): JsonResponse
    {
        $actor = $this->authorizeAdministrator($request);
        $resetStatus = Password::sendResetLink(['email' => $user->email]);

        if ($resetStatus !== Password::RESET_LINK_SENT) {
            return response()->json([
                'message' => 'The password reset email could not be sent.',
            ], 503);
        }

        $this->recordActivity($actor, $user, 'password_reset_requested', "Requested a password reset for {$user->email}.");

        return response()->json([
            'message' => 'A password reset link has been sent by email.',
        ]);
    }

    private function authorizeAdministrator(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->role === 'System Administrator' && $user->is_active, 403);

        return $user;
    }

    private function ensureAdministratorRemains(User $user, string $newRole, bool $newStatus): void
    {
        if (
            $user->role === 'System Administrator'
            && $user->is_active
            && ($newRole !== 'System Administrator' || ! $newStatus)
            && User::query()
                ->where('role', 'System Administrator')
                ->where('is_active', true)
                ->lockForUpdate()
                ->get(['id'])
                ->count() <= 1
        ) {
            abort(422, 'The last active System Administrator cannot be demoted or deactivated.');
        }
    }

    private function recordActivity(User $actor, User $user, string $eventType, string $description): void
    {
        SystemActivity::query()->create([
            'actor_user_id' => $actor->id,
            'user_id' => $user->id,
            'event_type' => $eventType,
            'description' => $description,
            'ip_address' => request()->ip(),
        ]);
    }

    /**
     * @return array{id: int, name: string, email: string, role: string, department: ?string, isActive: bool, createdAt: ?string}
     */
    private function userData(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'department' => $user->department,
            'isActive' => $user->is_active,
            'createdAt' => $user->created_at?->toIso8601String(),
        ];
    }
}
