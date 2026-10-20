<?php

namespace App\Http\Controllers;

use App\InventoryBatchQualityStatus;
use App\InventoryMovementType;
use App\Models\InventoryBatch;
use App\Models\InventoryMovement;
use App\Models\Material;
use App\Models\ProductionMaterialRequest;
use App\Models\User;
use App\ProcurementQuantity;
use App\ProductionMaterialRequestStatus;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProductionWorkspaceController extends Controller
{
    private const APPROVER_ROLES = ['Management', 'System Administrator'];

    private const ACCESS_ROLES = [
        'Production Staff',
        'Warehouse Staff',
        'Management',
        'System Administrator',
    ];

    public function dashboard(Request $request): JsonResponse
    {
        $user = $this->authorizeRole($request, ['Production Staff']);
        $materials = $this->materialInventory();
        $requests = ProductionMaterialRequest::query()->where('requested_by_user_id', $user->getKey());
        $requestNumbers = (clone $requests)->pluck('request_number');
        $issuedCount = $requestNumbers->isEmpty()
            ? 0
            : InventoryMovement::query()
                ->where('movement_type', InventoryMovementType::StockOut->value)
                ->where('reason', 'Approved production material request')
                ->whereIn('source_reference', $requestNumbers)
                ->count();

        return response()->json([
            'metrics' => [
                'availableMaterials' => $materials['materials']->count(),
                'materialsNeedingAttention' => $materials['materials']
                    ->filter(fn (array $material): bool => $material['availableQuantity'] <= $material['minimumQuantity'])
                    ->count(),
                'batchesExpiringSoon' => $materials['batches']
                    ->filter(fn (array $batch): bool => $batch['eligible'] && $batch['materialAvailableQuantity'] > 0
                        && $batch['daysUntilExpiry'] !== null
                        && $batch['daysUntilExpiry'] >= 0 && $batch['daysUntilExpiry'] <= 30)
                    ->count(),
                'pendingRequests' => (clone $requests)
                    ->where('status', ProductionMaterialRequestStatus::PendingApproval->value)
                    ->count(),
                'issuedMaterials' => $issuedCount,
            ],
            'expiringBatches' => $materials['batches']
                ->filter(fn (array $batch): bool => $batch['eligible'] && $batch['materialAvailableQuantity'] > 0
                    && $batch['daysUntilExpiry'] !== null
                    && $batch['daysUntilExpiry'] >= 0 && $batch['daysUntilExpiry'] <= 30)
                ->take(5)
                ->values(),
            'pendingRequests' => (clone $requests)
                ->where('status', ProductionMaterialRequestStatus::PendingApproval->value)
                ->with('material:id,code,name')
                ->latest()
                ->limit(5)
                ->get()
                ->map(fn (ProductionMaterialRequest $materialRequest): array => [
                    'requestNumber' => $materialRequest->request_number,
                    'material' => $materialRequest->material->name,
                    'quantity' => $materialRequest->requested_qty,
                    'status' => $materialRequest->status->value,
                ]),
        ])->header('Cache-Control', 'no-store');
    }

    public function materials(Request $request): JsonResponse
    {
        $this->authorizeRole($request, ['Production Staff']);
        $data = $this->materialInventory();

        return response()->json([
            'materials' => $data['materials']->values(),
            'batches' => $data['batches']->values(),
        ])->header('Cache-Control', 'no-store');
    }

    public function requests(Request $request): JsonResponse
    {
        $user = $this->authorizeRole($request, self::ACCESS_ROLES);
        $requests = $this->scopedRequests($user)
            ->with([
                'material:id,code,name,unit_of_measure_id',
                'material.unitOfMeasure:id,code',
                'requester:id,name',
                'approver:id,name',
            ])
            ->latest()
            ->limit(200)
            ->get();
        $requestNumbers = $requests->pluck('request_number');
        $fulfillments = $requestNumbers->isEmpty()
            ? collect()
            : InventoryMovement::query()
                ->with([
                    'inventoryBatch:id,lot_code,quantity_on_hand',
                    'actor:id,name',
                ])
                ->where('movement_type', InventoryMovementType::StockOut->value)
                ->where('reason', 'Approved production material request')
                ->whereIn('source_reference', $requestNumbers)
                ->latest('occurred_at')
                ->get()
                ->groupBy('source_reference');
        $inventory = $this->materialInventory();
        $materialsWithUsableStock = $inventory['materials']
            ->filter(fn (array $material): bool => $material['availableQuantity'] > 0)
            ->pluck('id');

        return response()->json([
            'requests' => $requests->map(fn (ProductionMaterialRequest $materialRequest): array => $this->requestData(
                $materialRequest,
                $fulfillments->get($materialRequest->request_number, collect()),
            )),
            'materials' => $inventory['materials']->values(),
            'availableBatches' => $inventory['batches']
                ->filter(fn (array $batch): bool => $batch['eligible']
                    && $materialsWithUsableStock->contains($batch['materialId']))
                ->values(),
            'canApprove' => in_array($user->role, self::APPROVER_ROLES, true),
            'canFulfill' => $user->role === 'Warehouse Staff',
            'canCreate' => $user->role === 'Production Staff',
        ])->header('Cache-Control', 'no-store');
    }

    public function storeRequest(Request $request): JsonResponse
    {
        $actor = $this->authorizeRole($request, ['Production Staff']);
        $validated = $request->validate([
            'request_number' => [
                'required',
                'string',
                'max:64',
                Rule::unique('production_material_requests', 'request_number'),
            ],
            'material_id' => [
                'required',
                'integer',
                Rule::exists('materials', 'id')->where('is_active', true),
            ],
            'requested_qty' => ['required', 'numeric', 'decimal:0,3', 'gt:0', 'max:99999999999.999'],
            'production_order' => ['nullable', 'string', 'max:120'],
            'purpose' => ['required', 'string', 'max:2000'],
            'required_date' => ['nullable', 'date', 'after_or_equal:today'],
        ]);

        $materialRequest = ProductionMaterialRequest::query()->create([
            ...$validated,
            'requested_by_user_id' => $actor->getKey(),
        ]);

        return response()->json([
            'request' => $this->requestData($materialRequest->load([
                'material:id,code,name,unit_of_measure_id',
                'material.unitOfMeasure:id,code',
                'requester:id,name',
                'approver:id,name',
            ]), collect()),
            'message' => 'Material request submitted for Management approval.',
        ], 201)->header('Cache-Control', 'no-store');
    }

    public function decideRequest(Request $request, ProductionMaterialRequest $materialRequest): JsonResponse
    {
        $approver = $this->authorizeRole($request, self::APPROVER_ROLES);
        $validated = $request->validate([
            'decision' => ['required', Rule::in(['approve', 'reject'])],
            'comments' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $materialRequest->recordDecision(
                $approver,
                $validated['decision'] === 'approve',
                $validated['comments'] ?? null,
            );
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['request' => $exception->getMessage()]);
        }

        return response()->json([
            'request' => $this->requestData($materialRequest->fresh([
                'material:id,code,name,unit_of_measure_id',
                'material.unitOfMeasure:id,code',
                'requester:id,name',
                'approver:id,name',
            ]), collect()),
            'message' => 'Material request decision recorded.',
        ])->header('Cache-Control', 'no-store');
    }

    public function fulfillRequest(Request $request, ProductionMaterialRequest $materialRequest): JsonResponse
    {
        $actor = $this->authorizeRole($request, ['Warehouse Staff']);
        $validated = $request->validate([
            'inventory_batch_id' => ['required', 'integer', 'exists:inventory_batches,id'],
            'quantity' => ['required', 'numeric', 'decimal:0,3', 'gt:0', 'max:99999999999.999'],
            'destination_location' => ['required', 'string', 'max:120'],
        ]);
        $batch = InventoryBatch::query()->findOrFail($validated['inventory_batch_id']);

        try {
            $materialRequest->recordFulfillment(
                $batch,
                $validated['quantity'],
                $actor,
                $validated['destination_location'],
            );
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['quantity' => $exception->getMessage()]);
        }

        return response()->json([
            'request' => $this->requestData($materialRequest->fresh([
                'material:id,code,name,unit_of_measure_id',
                'material.unitOfMeasure:id,code',
                'requester:id,name',
                'approver:id,name',
            ]), collect()),
            'message' => 'Material issued to production and inventory updated.',
        ])->header('Cache-Control', 'no-store');
    }

    public function issuedMaterials(Request $request): JsonResponse
    {
        $user = $this->authorizeRole($request, self::ACCESS_ROLES);
        $productionRequests = $this->scopedRequests($user)->get(['request_number', 'production_order']);
        $productionOrders = $productionRequests->pluck('production_order', 'request_number');
        $requestNumbers = $productionRequests->pluck('request_number');
        $movements = $requestNumbers->isEmpty()
            ? collect()
            : InventoryMovement::query()
                ->with([
                    'material:id,code,name,unit_of_measure_id',
                    'material.unitOfMeasure:id,code',
                    'inventoryBatch:id,lot_code,quantity_on_hand',
                    'actor:id,name',
                ])
                ->where('movement_type', InventoryMovementType::StockOut->value)
                ->where('reason', 'Approved production material request')
                ->whereIn('source_reference', $requestNumbers)
                ->latest('occurred_at')
                ->limit(300)
                ->get();

        return response()->json([
            'issues' => $movements->map(fn (InventoryMovement $movement): array => [
                'id' => $movement->getKey(),
                'requestNumber' => $movement->source_reference,
                'materialCode' => $movement->material->code,
                'material' => $movement->material->name,
                'unit' => $movement->material->unitOfMeasure->code,
                'batch' => $movement->inventoryBatch?->lot_code ?? '—',
                'quantity' => $movement->quantity,
                'remaining' => $movement->inventoryBatch?->quantity_on_hand ?? '0.000',
                'productionOrder' => $productionOrders->get($movement->source_reference) ?? '—',
                'destination' => $movement->destination_location ?? '—',
                'issuedBy' => $movement->actor?->name ?? '—',
                'issuedAt' => $movement->occurred_at?->toIso8601String(),
            ]),
        ])->header('Cache-Control', 'no-store');
    }

    public function batches(Request $request): JsonResponse
    {
        $this->authorizeRole($request, ['Production Staff']);
        $data = $this->materialInventory();

        return response()->json([
            'batches' => $data['batches']->values(),
        ])->header('Cache-Control', 'no-store');
    }

    private function authorizeRole(Request $request, array $roles): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        abort_unless(in_array($user->role, $roles, true), 403);

        return $user;
    }

    private function scopedRequests(User $user): Builder
    {
        $query = ProductionMaterialRequest::query();

        if ($user->role === 'Production Staff') {
            $query->where('requested_by_user_id', $user->getKey());
        } elseif ($user->role === 'Warehouse Staff') {
            $query->whereIn('status', [
                ProductionMaterialRequestStatus::Approved->value,
                ProductionMaterialRequestStatus::PartiallyFulfilled->value,
            ]);
        }

        return $query;
    }

    /**
     * @return array{materials: Collection<int, array<string, mixed>>, batches: Collection<int, array<string, mixed>>}
     */
    private function materialInventory(): array
    {
        $materials = Material::query()
            ->where('is_active', true)
            ->with([
                'category:id,name',
                'unitOfMeasure:id,code',
                'inventoryBatches' => fn (HasMany $query) => $query
                    ->where('quantity_on_hand', '>', 0)
                    ->orderByRaw('expires_at IS NULL')
                    ->orderBy('expires_at')
                    ->orderBy('received_at')
                    ->orderBy('id'),
                'stockReservations',
            ])
            ->orderBy('code')
            ->get();
        $batches = collect();

        $materialRows = $materials->map(function (Material $material) use ($batches): array {
            $stockPosition = $material->stockPosition();
            $availableBatches = $material->inventoryBatches
                ->filter(fn (InventoryBatch $batch): bool => $this->isEligibleBatch($batch))
                ->values();
            $materialAvailableMilli = ProcurementQuantity::toMilliUnits($stockPosition['available_to_use']);
            $recommendedBatchId = $materialAvailableMilli > 0
                ? $availableBatches->first()?->getKey()
                : null;

            foreach ($material->inventoryBatches as $batch) {
                $batches->push($this->batchData(
                    $material,
                    $batch,
                    $recommendedBatchId === $batch->getKey(),
                    $materialAvailableMilli,
                ));
            }

            return [
                'id' => $material->getKey(),
                'code' => $material->code,
                'name' => $material->name,
                'category' => $material->category?->name ?? 'Uncategorized',
                'unit' => $material->unitOfMeasure->code,
                'availableQuantity' => $stockPosition['available_to_use'],
                'reservedQuantity' => $stockPosition['reserved'],
                'minimumQuantity' => $material->minimum_stock_qty,
                'batchCount' => $material->inventoryBatches->count(),
                'usableBatchCount' => $availableBatches->count(),
            ];
        });

        return [
            'materials' => $materialRows,
            'batches' => $batches,
        ];
    }

    private function isEligibleBatch(InventoryBatch $batch): bool
    {
        return $batch->quality_status === InventoryBatchQualityStatus::Available
            && ($batch->expires_at === null || ! $batch->expires_at->isBefore(today()))
            && ProcurementQuantity::toMilliUnits((string) $batch->quantity_on_hand) > 0;
    }

    /**
     * @return array<string, mixed>
     */
    private function batchData(
        Material $material,
        InventoryBatch $batch,
        bool $recommended,
        int $materialAvailableMilli,
    ): array {
        $eligible = $this->isEligibleBatch($batch);
        $daysUntilExpiry = $batch->expires_at === null
            ? null
            : (int) today()->diffInDays($batch->expires_at, false);

        return [
            'id' => $batch->getKey(),
            'materialId' => $material->getKey(),
            'materialCode' => $material->code,
            'material' => $material->name,
            'unit' => $material->unitOfMeasure->code,
            'lotCode' => $batch->lot_code,
            'quantityOnHand' => $batch->quantity_on_hand,
            'qualityStatus' => $batch->quality_status->value,
            'expiresAt' => $batch->expires_at?->toDateString(),
            'daysUntilExpiry' => $daysUntilExpiry,
            'storageLocation' => $batch->storage_location ?? '—',
            'receivedAt' => $batch->received_at?->toIso8601String(),
            'eligible' => $eligible,
            'recommended' => $eligible && $recommended,
            'materialAvailableQuantity' => ProcurementQuantity::fromMilliUnits($materialAvailableMilli),
        ];
    }

    /**
     * @param  Collection<int, InventoryMovement>  $movements
     * @return array<string, mixed>
     */
    private function requestData(ProductionMaterialRequest $request, Collection $movements): array
    {
        $requestedMilli = ProcurementQuantity::toMilliUnits((string) $request->requested_qty);
        $fulfilledMilli = ProcurementQuantity::toMilliUnits((string) $request->fulfilled_qty);

        return [
            'id' => $request->getKey(),
            'requestNumber' => $request->request_number,
            'materialId' => $request->material_id,
            'materialCode' => $request->material?->code,
            'material' => $request->material?->name,
            'unit' => $request->material?->unitOfMeasure?->code,
            'requestedQuantity' => $request->requested_qty,
            'fulfilledQuantity' => $request->fulfilled_qty,
            'remainingQuantity' => ProcurementQuantity::fromMilliUnits(max(0, $requestedMilli - $fulfilledMilli)),
            'productionOrder' => $request->production_order,
            'purpose' => $request->purpose,
            'requiredDate' => $request->required_date?->toDateString(),
            'status' => $request->status->value,
            'requester' => $request->requester?->name ?? '—',
            'approver' => $request->approver?->name,
            'decidedAt' => $request->decided_at?->toIso8601String(),
            'decisionNotes' => $request->decision_notes,
            'createdAt' => $request->created_at?->toIso8601String(),
            'fulfillments' => $movements->map(fn (InventoryMovement $movement): array => [
                'batch' => $movement->inventoryBatch?->lot_code ?? '—',
                'quantity' => $movement->quantity,
                'issuedBy' => $movement->actor?->name ?? '—',
                'issuedAt' => $movement->occurred_at?->toIso8601String(),
            ])->values(),
        ];
    }
}
