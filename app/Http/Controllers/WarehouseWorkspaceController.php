<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class WarehouseWorkspaceController extends Controller
{
    private const PRODUCT_STATUSES = [
        'Available',
        'Aging',
        'For Production',
        'For Distribution',
        'Expired',
        'Damaged',
    ];

    public function updateProductStatus(Request $request, string $productId): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in(self::PRODUCT_STATUSES)],
        ]);
        $timestamp = now();

        DB::table('product_monitor_statuses')->upsert([
            [
                'product_id' => $productId,
                'status' => $validated['status'],
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
        ], ['product_id'], ['status', 'updated_at']);

        return response()->json([
            'productId' => $productId,
            'status' => $validated['status'],
        ]);
    }

    public function markAlertRead(Request $request, string $alertId): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $this->persistReadAlerts($user, [$alertId]);

        return response()->json([
            'readAlertIds' => [$alertId],
        ]);
    }

    public function markAlertUnread(Request $request, string $alertId): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        DB::table('product_monitor_alert_reads')
            ->where('user_id', $user->id)
            ->where('alert_id', $alertId)
            ->delete();

        return response()->json([
            'unreadAlertIds' => [$alertId],
        ]);
    }

    public function markAlertsRead(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $validated = $request->validate([
            'alert_ids' => ['present', 'array', 'max:100'],
            'alert_ids.*' => ['required', 'string', 'max:64', 'distinct', 'regex:/^[a-z]+-[A-Za-z0-9-]+$/'],
        ]);

        $this->persistReadAlerts($user, $validated['alert_ids']);

        return response()->json([
            'readAlertIds' => $validated['alert_ids'],
        ]);
    }

    /**
     * @param  list<string>  $alertIds
     */
    private function persistReadAlerts(User $user, array $alertIds): void
    {
        if ($alertIds === []) {
            return;
        }

        $timestamp = now();
        $readAlerts = array_map(
            fn (string $alertId): array => [
                'user_id' => $user->id,
                'alert_id' => $alertId,
                'read_at' => $timestamp,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            $alertIds,
        );

        DB::table('product_monitor_alert_reads')->upsert(
            $readAlerts,
            ['user_id', 'alert_id'],
            ['read_at', 'updated_at'],
        );
    }
}
