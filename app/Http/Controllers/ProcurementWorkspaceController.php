<?php

namespace App\Http\Controllers;

use App\CurrencyCodes;
use App\DeliveryStatus;
use App\Http\Requests\Procurement\StoreDeliveryRequest;
use App\Http\Requests\Procurement\StoreGoodsReceiptRequest;
use App\Http\Requests\Procurement\StorePurchaseOrderRequest;
use App\Http\Requests\Procurement\StorePurchaseRequestApprovalRequest;
use App\Http\Requests\Procurement\StorePurchaseRequestRequest;
use App\Http\Requests\Procurement\StoreSupplierRequest;
use App\InventoryMovementType;
use App\Models\Delivery;
use App\Models\DeliveryItem;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptItem;
use App\Models\InventoryMovement;
use App\Models\Material;
use App\Models\ProcurementActivity;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\Supplier;
use App\Models\SupplierMaterial;
use App\Models\User;
use App\ProcurementDashboardReport;
use App\ProcurementQuantity;
use App\PurchaseRequestApprovalDecision;
use App\PurchaseRequestStatus;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProcurementWorkspaceController extends Controller
{
    private const ACCESSIBLE_ROLES = [
        'Procurement Staff',
        'Management',
        'System Administrator',
    ];

    public function dashboard(Request $request): JsonResponse
    {
        $this->authorizeWorkspaceAccess($request);
        $report = (new ProcurementDashboardReport)->snapshot(activityLimit: 8);
        $materialsAtRisk = collect($this->materialRequirements())
            ->filter(fn (array $material): bool => $material['risk'] !== 'sufficient');
        $pendingRequests = PurchaseRequest::query()
            ->where('status', PurchaseRequestStatus::Submitted->value)
            ->count();
        $delayedSupplierCount = $report['overdue_deliveries']
            ->pluck('supplier_id')
            ->unique()
            ->count();

        return response()->json([
            'asOf' => $report['as_of'],
            'metrics' => [
                'pendingMaterialRequests' => $pendingRequests,
                'potentialShortages' => $materialsAtRisk->count(),
                'pendingPurchaseRequests' => $report['kpis']['approved_unpurchased_request_item_count'],
                'expectedDeliveries' => $report['expected_deliveries']->count(),
                'supplierRisks' => $delayedSupplierCount,
            ],
            'lowStockMaterials' => $materialsAtRisk
                ->take(5)
                ->map(fn (array $material): array => [
                    'id' => $material['id'],
                    'code' => $material['code'],
                    'name' => $material['name'],
                    'available' => $material['available'],
                    'minimum' => $material['minimum'],
                    'unit' => $material['unit'],
                ])
                ->values(),
            'expectedDeliveries' => $report['expected_deliveries']
                ->take(5)
                ->map(fn (Delivery $delivery): array => [
                    'id' => $delivery->getKey(),
                    'number' => $delivery->delivery_number,
                    'supplier' => $delivery->supplier->name,
                    'purchaseOrder' => $delivery->purchaseOrder->order_number,
                    'expectedDate' => $delivery->expected_arrival_date?->toDateString(),
                    'status' => $delivery->status->value,
                ])
                ->values(),
            'recentActivity' => $report['recent_activities']
                ->map(fn (ProcurementActivity $activity): array => [
                    'description' => $activity->description,
                    'actor' => $activity->actor?->name,
                    'occurredAt' => $activity->occurred_at?->toIso8601String(),
                ]),
        ])->header('Cache-Control', 'no-store');
    }

    public function materials(Request $request): JsonResponse
    {
        $this->authorizeWorkspaceAccess($request);

        return response()->json([
            'materials' => $this->materialRequirements(),
        ])->header('Cache-Control', 'no-store');
    }

    public function requests(Request $request): JsonResponse
    {
        $this->authorizeWorkspaceAccess($request);

        return response()->json([
            'requests' => PurchaseRequest::query()
                ->with([
                    'requester:id,name',
                    'items.material:id,code,name,unit_of_measure_id',
                    'items.unitOfMeasure:id,code',
                    'approvals.approver:id,name',
                ])
                ->withCount('items')
                ->orderByDesc('request_date')
                ->orderByDesc('id')
                ->limit(100)
                ->get()
                ->map(fn (PurchaseRequest $purchaseRequest): array => $this->requestData($purchaseRequest)),
            'orders' => PurchaseOrder::query()
                ->with([
                    'supplier:id,name',
                    'items.material:id,code,name',
                    'items.unitOfMeasure:id,code',
                ])
                ->orderByDesc('order_date')
                ->orderByDesc('id')
                ->limit(100)
                ->get()
                ->map(fn (PurchaseOrder $purchaseOrder): array => $this->orderData($purchaseOrder)),
            'approvedRequestItems' => PurchaseRequestItem::query()
                ->with([
                    'purchaseRequest:id,request_number,status',
                    'material:id,code,name',
                    'unitOfMeasure:id,code',
                ])
                ->whereHas('purchaseRequest', fn (Builder $query) => $query
                    ->where('status', PurchaseRequestStatus::Approved->value))
                ->orderBy('id')
                ->get()
                ->map(fn (PurchaseRequestItem $item): array => [
                    'id' => $item->getKey(),
                    'requestNumber' => $item->purchaseRequest->request_number,
                    'materialId' => $item->material_id,
                    'material' => $item->material->name,
                    'code' => $item->material->code,
                    'unit' => $item->unitOfMeasure->code,
                    'remainingQuantity' => ProcurementQuantity::fromMilliUnits($item->remainingQuantityMilli()),
                ])
                ->filter(fn (array $item): bool => ProcurementQuantity::toMilliUnits($item['remainingQuantity']) > 0)
                ->values(),
            'suppliers' => Supplier::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'supplierMaterials' => SupplierMaterial::query()
                ->with(['supplier:id,name', 'material:id,code,name'])
                ->where('is_active', true)
                ->orderBy('supplier_id')
                ->get()
                ->map(fn (SupplierMaterial $supplierMaterial): array => [
                    'id' => $supplierMaterial->getKey(),
                    'supplierId' => $supplierMaterial->supplier_id,
                    'materialId' => $supplierMaterial->material_id,
                    'supplier' => $supplierMaterial->supplier->name,
                    'material' => $supplierMaterial->material->name,
                    'minimumOrderQuantity' => $supplierMaterial->minimum_order_qty,
                    'quotedUnitPrice' => $supplierMaterial->quoted_unit_price,
                    'currency' => $supplierMaterial->currency_code,
                ]),
            'materials' => Material::query()
                ->where('is_active', true)
                ->with('unitOfMeasure:id,code')
                ->orderBy('code')
                ->get(['id', 'code', 'name', 'unit_of_measure_id'])
                ->map(fn (Material $material): array => [
                    'id' => $material->getKey(),
                    'code' => $material->code,
                    'name' => $material->name,
                    'unit' => $material->unitOfMeasure->code,
                ]),
            'currencies' => CurrencyCodes::ISO_4217,
        ])->header('Cache-Control', 'no-store');
    }

    public function storeRequest(StorePurchaseRequestRequest $request): JsonResponse
    {
        $actor = $this->authorizeProcurementStaff($request);
        $line = $request->validate([
            'material_id' => ['required', 'integer', Rule::exists('materials', 'id')->where('is_active', true)],
            'requested_qty' => ['required', 'numeric', 'decimal:0,3', 'gt:0', 'max:99999999999.999'],
            'justification' => ['nullable', 'string', 'max:10000'],
        ]);

        $purchaseRequest = DB::transaction(function () use ($actor, $line, $request): PurchaseRequest {
            $purchaseRequest = new PurchaseRequest($request->validated());
            $purchaseRequest->requested_by_user_id = $actor->getKey();
            $purchaseRequest->save();
            $material = Material::query()->findOrFail($line['material_id']);

            $purchaseRequest->items()->create([
                'material_id' => $material->getKey(),
                'unit_of_measure_id' => $material->unit_of_measure_id,
                'requested_qty' => $line['requested_qty'],
                'justification' => $line['justification'] ?? null,
            ]);

            return $purchaseRequest;
        });

        return response()->json([
            'request' => $this->requestData($purchaseRequest->load([
                'requester:id,name',
                'items.material:id,code,name,unit_of_measure_id',
                'items.unitOfMeasure:id,code',
                'approvals.approver:id,name',
            ])),
            'message' => 'Purchase request saved as a draft. Submit it for approval when ready.',
        ], 201);
    }

    public function submitRequest(Request $request, PurchaseRequest $purchaseRequest): JsonResponse
    {
        $actor = $this->authorizeProcurementStaff($request);
        abort_unless((int) $purchaseRequest->requested_by_user_id === (int) $actor->getKey(), 403);

        try {
            $purchaseRequest->submit();
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['request' => $exception->getMessage()]);
        }

        return response()->json([
            'request' => $this->requestData($purchaseRequest->refresh()->load([
                'requester:id,name',
                'items.material:id,code,name,unit_of_measure_id',
                'items.unitOfMeasure:id,code',
                'approvals.approver:id,name',
            ])),
            'message' => 'Purchase request submitted for approval.',
        ]);
    }

    public function decideRequest(
        StorePurchaseRequestApprovalRequest $request,
        PurchaseRequest $purchaseRequest,
    ): JsonResponse {
        $approver = $this->authorizeApprover($request);
        $validated = $request->validated();
        abort_unless((int) $validated['purchase_request_id'] === (int) $purchaseRequest->getKey(), 422);

        try {
            $purchaseRequest->recordDecision(
                $approver,
                PurchaseRequestApprovalDecision::from($validated['decision']),
                $validated['comments'] ?? null,
            );
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['request' => $exception->getMessage()]);
        }

        return response()->json([
            'request' => $this->requestData($purchaseRequest->refresh()->load([
                'requester:id,name',
                'items.material:id,code,name,unit_of_measure_id',
                'items.unitOfMeasure:id,code',
                'approvals.approver:id,name',
            ])),
            'message' => 'Purchase request decision recorded.',
        ]);
    }

    public function storeOrder(StorePurchaseOrderRequest $request): JsonResponse
    {
        $actor = $this->authorizeProcurementStaff($request);
        $lines = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.purchase_request_item_id' => ['required', 'integer', 'distinct', 'exists:purchase_request_items,id'],
            'items.*.supplier_material_id' => ['nullable', 'integer', 'exists:supplier_materials,id'],
            'items.*.ordered_qty' => ['required', 'numeric', 'decimal:0,3', 'gt:0', 'max:99999999999.999'],
            'items.*.unit_price' => ['required', 'numeric', 'decimal:0,4', 'min:0', 'max:9999999999.9999'],
            'items.*.notes' => ['nullable', 'string', 'max:10000'],
        ]);

        try {
            $purchaseOrder = DB::transaction(function () use ($actor, $lines, $request): PurchaseOrder {
                $purchaseOrder = new PurchaseOrder($request->validated());
                $purchaseOrder->created_by_user_id = $actor->getKey();
                $purchaseOrder->save();

                foreach ($lines['items'] as $line) {
                    $requestItem = PurchaseRequestItem::query()
                        ->with('purchaseRequest')
                        ->findOrFail($line['purchase_request_item_id']);
                    $supplierMaterial = isset($line['supplier_material_id'])
                        ? SupplierMaterial::query()->findOrFail($line['supplier_material_id'])
                        : null;

                    if ($supplierMaterial !== null
                        && ((int) $supplierMaterial->supplier_id !== (int) $purchaseOrder->supplier_id
                            || (int) $supplierMaterial->material_id !== (int) $requestItem->material_id
                            || ! $supplierMaterial->is_active)) {
                        throw ValidationException::withMessages([
                            'items' => 'Supplier-material terms must match the selected supplier and approved material.',
                        ]);
                    }

                    $purchaseOrderItem = $purchaseOrder->items()->create([
                        'material_id' => $requestItem->material_id,
                        'unit_of_measure_id' => $requestItem->unit_of_measure_id,
                        'supplier_material_id' => $supplierMaterial?->getKey(),
                        'ordered_qty' => $line['ordered_qty'],
                        'unit_price' => $line['unit_price'],
                        'notes' => $line['notes'] ?? null,
                    ]);
                    $requestItem->allocateTo($purchaseOrderItem, $line['ordered_qty']);
                }

                return $purchaseOrder;
            });
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['items' => $exception->getMessage()]);
        }

        return response()->json([
            'order' => $this->orderData($purchaseOrder->load([
                'supplier:id,name',
                'items.material:id,code,name',
                'items.unitOfMeasure:id,code',
            ])),
            'message' => 'Draft purchase order created and linked to approved request items.',
        ], 201);
    }

    public function placeOrder(Request $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        $actor = $this->authorizeProcurementStaff($request);
        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:10000'],
        ]);

        try {
            $purchaseOrder->place($actor, $validated['notes'] ?? null);
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['order' => $exception->getMessage()]);
        }

        return response()->json([
            'order' => $this->orderData($purchaseOrder->refresh()->load([
                'supplier:id,name',
                'items.material:id,code,name',
                'items.unitOfMeasure:id,code',
            ])),
            'message' => 'Purchase order placed with the supplier.',
        ]);
    }

    public function suppliers(Request $request): JsonResponse
    {
        $this->authorizeWorkspaceAccess($request);

        return response()->json([
            'suppliers' => Supplier::query()
                ->withCount(['purchaseOrders', 'deliveries'])
                ->with([
                    'supplierMaterials.material:id,code,name',
                    'deliveries' => fn ($query) => $query
                        ->with('purchaseOrder:id,order_number')
                        ->orderByDesc('id')
                        ->limit(5),
                ])
                ->orderBy('name')
                ->limit(100)
                ->get()
                ->map(fn (Supplier $supplier): array => [
                    'id' => $supplier->getKey(),
                    'code' => $supplier->supplier_code,
                    'name' => $supplier->name,
                    'contactPerson' => $supplier->contact_person,
                    'email' => $supplier->contact_email,
                    'phone' => $supplier->contact_phone,
                    'active' => $supplier->is_active,
                    'purchaseOrderCount' => $supplier->purchase_orders_count,
                    'deliveryCount' => $supplier->deliveries_count,
                    'materials' => $supplier->supplierMaterials
                        ->map(fn (SupplierMaterial $supplierMaterial): array => [
                            'name' => $supplierMaterial->material->name,
                            'sku' => $supplierMaterial->supplier_sku,
                            'leadTimeDays' => $supplierMaterial->lead_time_days,
                            'preferred' => $supplierMaterial->is_preferred,
                        ])
                        ->values(),
                    'recentDeliveries' => $supplier->deliveries
                        ->map(fn (Delivery $delivery): array => [
                            'number' => $delivery->delivery_number,
                            'purchaseOrder' => $delivery->purchaseOrder->order_number,
                            'expectedDate' => $delivery->expected_arrival_date?->toDateString(),
                            'actualDate' => $delivery->actual_arrival_at?->toDateString(),
                            'status' => $delivery->status->value,
                        ])
                        ->values(),
                ]),
        ])->header('Cache-Control', 'no-store');
    }

    public function storeSupplier(StoreSupplierRequest $request): JsonResponse
    {
        $this->authorizeProcurementStaff($request);
        $supplier = Supplier::query()->create([
            ...$request->validated(),
            'is_active' => true,
        ]);

        return response()->json([
            'supplier' => [
                'id' => $supplier->getKey(),
                'code' => $supplier->supplier_code,
                'name' => $supplier->name,
                'contactPerson' => $supplier->contact_person,
                'email' => $supplier->contact_email,
                'phone' => $supplier->contact_phone,
                'active' => $supplier->is_active,
            ],
            'message' => 'Supplier added.',
        ], 201);
    }

    public function deliveries(Request $request): JsonResponse
    {
        $this->authorizeWorkspaceAccess($request);

        return response()->json([
            'deliveries' => Delivery::query()
                ->with([
                    'supplier:id,name',
                    'purchaseOrder:id,order_number',
                    'items.purchaseOrderItem.material:id,code,name',
                    'items.goodsReceiptItems.goodsReceipt:id,status,received_at,posted_at',
                    'items.goodsReceiptItems.inventoryBatch:id,lot_code',
                ])
                ->orderByDesc('expected_arrival_date')
                ->orderByDesc('id')
                ->limit(100)
                ->get()
                ->map(fn (Delivery $delivery): array => $this->deliveryData($delivery)),
            'orderedOrders' => PurchaseOrder::query()
                ->where('status', 'ordered')
                ->with([
                    'supplier:id,name',
                    'items.material:id,code,name',
                    'items.unitOfMeasure:id,code',
                    'items.deliveryItems.delivery',
                    'items.goodsReceiptItems.goodsReceipt',
                ])
                ->orderBy('expected_delivery_date')
                ->get()
                ->map(fn (PurchaseOrder $purchaseOrder): array => [
                    ...$this->orderData($purchaseOrder),
                    'items' => $purchaseOrder->items->map(fn (PurchaseOrderItem $item): array => [
                        'id' => $item->getKey(),
                        'material' => $item->material->name,
                        'code' => $item->material->code,
                        'unit' => $item->unitOfMeasure->code,
                        'orderedQuantity' => $item->ordered_qty,
                        'remainingDispatchQuantity' => ProcurementQuantity::fromMilliUnits(
                            max(0, ProcurementQuantity::toMilliUnits((string) $item->ordered_qty) - $item->dispatchedQuantityMilli()),
                        ),
                    ])->values(),
                ]),
            'receiptDeliveries' => Delivery::query()
                ->whereIn('status', [DeliveryStatus::Dispatched->value, DeliveryStatus::PartiallyReceived->value])
                ->with([
                    'supplier:id,name',
                    'purchaseOrder:id,order_number',
                    'items.purchaseOrderItem.material:id,name',
                    'items.goodsReceiptItems.goodsReceipt',
                ])
                ->orderBy('expected_arrival_date')
                ->get()
                ->map(fn (Delivery $delivery): array => [
                    'id' => $delivery->getKey(),
                    'number' => $delivery->delivery_number,
                    'supplier' => $delivery->supplier->name,
                    'purchaseOrder' => $delivery->purchaseOrder->order_number,
                    'items' => $delivery->items->map(fn (DeliveryItem $item): array => [
                        'id' => $item->getKey(),
                        'material' => $item->purchaseOrderItem->material->name,
                        'remainingQuantity' => ProcurementQuantity::fromMilliUnits($item->remainingToReceiveMilli()),
                    ])->filter(fn (array $item): bool => ProcurementQuantity::toMilliUnits($item['remainingQuantity']) > 0)->values(),
                ]),
        ])->header('Cache-Control', 'no-store');
    }

    public function storeDelivery(StoreDeliveryRequest $request): JsonResponse
    {
        $actor = $this->authorizeProcurementStaff($request);
        $validatedItems = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.purchase_order_item_id' => ['required', 'integer', 'distinct', 'exists:purchase_order_items,id'],
            'items.*.dispatched_qty' => ['required', 'numeric', 'decimal:0,3', 'gt:0', 'max:99999999999.999'],
        ]);

        try {
            $delivery = DB::transaction(function () use ($actor, $request, $validatedItems): Delivery {
                $delivery = Delivery::query()->create([
                    ...$request->validated(),
                ]);

                foreach ($validatedItems['items'] as $item) {
                    $delivery->items()->create($item);
                }

                $delivery->dispatch($actor);

                return $delivery;
            });
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['items' => $exception->getMessage()]);
        }

        return response()->json([
            'delivery' => $this->deliveryData($delivery->load([
                'supplier:id,name',
                'purchaseOrder:id,order_number',
                'items.purchaseOrderItem.material:id,code,name',
                'items.goodsReceiptItems.goodsReceipt:id,status,received_at,posted_at',
                'items.goodsReceiptItems.inventoryBatch:id,lot_code',
            ])),
            'message' => 'Delivery record created and marked as dispatched.',
        ], 201);
    }

    public function storeReceipt(StoreGoodsReceiptRequest $request, Delivery $delivery): JsonResponse
    {
        $actor = $this->authorizeProcurementStaff($request);
        abort_unless(in_array($delivery->status, [
            DeliveryStatus::Dispatched,
            DeliveryStatus::PartiallyReceived,
        ], true), 422, 'Only active dispatched deliveries can be received.');
        $validated = $request->validate([
            'receipt_number' => ['required', 'string', 'max:64', 'unique:goods_receipts,receipt_number'],
            'delivery_id' => ['required', 'integer', Rule::in([$delivery->getKey()])],
            'notes' => ['nullable', 'string', 'max:10000'],
            'actual_received_at' => ['nullable', 'date'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.delivery_item_id' => ['required', 'integer', 'distinct', 'exists:delivery_items,id'],
            'items.*.accepted_qty' => ['required', 'numeric', 'decimal:0,3', 'gte:0', 'max:99999999999.999'],
            'items.*.rejected_qty' => ['required', 'numeric', 'decimal:0,3', 'gte:0', 'max:99999999999.999'],
            'items.*.lot_code' => ['nullable', 'string', 'max:100'],
            'items.*.expires_at' => ['nullable', 'date'],
            'items.*.discrepancy_notes' => ['nullable', 'string', 'max:10000'],
        ]);

        try {
            $receipt = DB::transaction(function () use ($actor, $delivery, $validated): GoodsReceipt {
                $receipt = GoodsReceipt::query()->create([
                    'receipt_number' => $validated['receipt_number'],
                    'delivery_id' => $delivery->getKey(),
                    'notes' => $validated['notes'] ?? null,
                ]);

                foreach ($validated['items'] as $item) {
                    $deliveryItem = DeliveryItem::query()->findOrFail($item['delivery_item_id']);

                    $receipt->items()->create([
                        'delivery_item_id' => $deliveryItem->getKey(),
                        'purchase_order_item_id' => $deliveryItem->purchase_order_item_id,
                        'accepted_qty' => $item['accepted_qty'],
                        'rejected_qty' => $item['rejected_qty'],
                        'lot_code' => $item['lot_code'] ?? null,
                        'expires_at' => $item['expires_at'] ?? null,
                        'discrepancy_notes' => $item['discrepancy_notes'] ?? null,
                    ]);
                }

                $receipt->post(
                    $actor,
                    isset($validated['actual_received_at'])
                        ? CarbonImmutable::parse($validated['actual_received_at'])
                        : null,
                );

                return $receipt;
            });
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['items' => $exception->getMessage()]);
        }

        return response()->json([
            'receipt' => [
                'id' => $receipt->getKey(),
                'number' => $receipt->receipt_number,
                'status' => $receipt->status->value,
                'receivedAt' => $receipt->received_at?->toIso8601String(),
            ],
            'message' => 'Receipt posted and accepted material added to inventory.',
        ], 201);
    }

    public function reports(Request $request): JsonResponse
    {
        $this->authorizeWorkspaceAccess($request);
        $report = (new ProcurementDashboardReport)->snapshot(activityLimit: 20);
        $materials = collect($this->materialRequirements());
        $frequentRequests = PurchaseRequestItem::query()
            ->with(['material:id,code,name', 'purchaseRequest:id,request_date'])
            ->whereHas('purchaseRequest', fn (Builder $query) => $query
                ->where('request_date', '>=', today()->subDays(90))
                ->whereNotIn('status', [PurchaseRequestStatus::Draft->value, PurchaseRequestStatus::Cancelled->value]))
            ->get()
            ->groupBy('material_id')
            ->map(fn (Collection $items): array => [
                'material' => $items->first()->material->name,
                'code' => $items->first()->material->code,
                'requestCount' => $items->count(),
                'quantity' => ProcurementQuantity::fromMilliUnits($items->sum(
                    fn (PurchaseRequestItem $item): int => ProcurementQuantity::toMilliUnits((string) $item->requested_qty),
                )),
            ])
            ->sortByDesc('requestCount')
            ->take(10)
            ->values();

        return response()->json([
            'replenishmentHistory' => $report['recent_activities']->map(fn (ProcurementActivity $activity): array => [
                'description' => $activity->description,
                'actor' => $activity->actor?->name,
                'date' => $activity->occurred_at?->toIso8601String(),
            ]),
            'frequentlyRequestedMaterials' => $frequentRequests,
            'delayedDeliveries' => $report['overdue_deliveries']->map(fn (Delivery $delivery): array => [
                'number' => $delivery->delivery_number,
                'supplier' => $delivery->supplier->name,
                'purchaseOrder' => $delivery->purchaseOrder->order_number,
                'expectedDate' => $delivery->expected_arrival_date?->toDateString(),
                'daysLate' => $delivery->expected_arrival_date?->diffInDays(today()),
            ]),
            'potentialOverstock' => $materials
                ->filter(fn (array $material): bool => ProcurementQuantity::toMilliUnits($material['available'])
                    > ProcurementQuantity::toMilliUnits($material['reorderTo']))
                ->map(fn (array $material): array => [
                    'name' => $material['name'],
                    'available' => $material['available'],
                    'reorderTo' => $material['reorderTo'],
                    'unit' => $material['unit'],
                ])
                ->values(),
            'stockoutRisks' => $materials
                ->filter(fn (array $material): bool => $material['risk'] !== 'sufficient')
                ->sortBy(fn (array $material): int => ProcurementQuantity::toMilliUnits($material['available']))
                ->take(20)
                ->values(),
        ])->header('Cache-Control', 'no-store');
    }

    private function authorizeWorkspaceAccess(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User
            && $user->is_active
            && in_array($user->role, self::ACCESSIBLE_ROLES, true), 403);

        return $user;
    }

    private function authorizeProcurementStaff(Request $request): User
    {
        $user = $this->authorizeWorkspaceAccess($request);
        abort_unless($user->role === 'Procurement Staff', 403);

        return $user;
    }

    private function authorizeApprover(Request $request): User
    {
        $user = $this->authorizeWorkspaceAccess($request);
        abort_unless(in_array($user->role, ['Management', 'System Administrator'], true), 403);

        return $user;
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function materialRequirements(): Collection
    {
        $materials = Material::query()
            ->where('is_active', true)
            ->with([
                'category:id,name',
                'unitOfMeasure:id,code',
                'inventoryBatches',
                'stockReservations',
            ])
            ->orderBy('code')
            ->get();
        $usageByMaterial = InventoryMovement::query()
            ->where('movement_type', InventoryMovementType::StockOut->value)
            ->where('occurred_at', '>=', now()->subDays(30))
            ->selectRaw('material_id, SUM(ABS(on_hand_delta)) as used_quantity')
            ->groupBy('material_id')
            ->pluck('used_quantity', 'material_id');
        $inboundByMaterial = PurchaseOrderItem::query()
            ->whereHas('purchaseOrder', fn (Builder $query) => $query->where('status', 'ordered'))
            ->with(['deliveryItems.delivery', 'goodsReceiptItems.goodsReceipt'])
            ->get()
            ->groupBy('material_id')
            ->map(fn (Collection $items): int => $items->sum(
                fn (PurchaseOrderItem $item): int => $item->openInboundMilli(),
            ));
        $approvedDemandByMaterial = PurchaseRequestItem::query()
            ->whereHas('purchaseRequest', fn (Builder $query) => $query
                ->where('status', PurchaseRequestStatus::Approved->value))
            ->with('purchaseOrderAllocations.purchaseOrderItem.purchaseOrder')
            ->get(['id', 'material_id', 'requested_qty'])
            ->groupBy('material_id')
            ->map(fn (Collection $items): int => $items->sum(
                fn (PurchaseRequestItem $item): int => $item->unpurchasedQuantityMilli(),
            ));

        return $materials->map(function (Material $material) use ($approvedDemandByMaterial, $inboundByMaterial, $usageByMaterial): array {
            $stock = $material->stockPosition();
            $inboundMilli = $inboundByMaterial->get($material->getKey(), 0);
            $approvedDemandMilli = $approvedDemandByMaterial->get($material->getKey(), 0);
            $usedQuantity = number_format((float) ($usageByMaterial->get($material->getKey()) ?? 0), 3, '.', '');
            $dailyUsageMilli = (int) round(ProcurementQuantity::toMilliUnits($usedQuantity) / 30);
            $availableMilli = ProcurementQuantity::toMilliUnits($stock['available_to_use']);
            $reorderToMilli = ProcurementQuantity::toMilliUnits((string) $material->reorder_to_qty);
            $uncoveredDemandMilli = max(0, $approvedDemandMilli - $availableMilli - $inboundMilli);
            $suggestedMilli = max(0, $reorderToMilli - $availableMilli - $inboundMilli, $uncoveredDemandMilli);
            $projectedCoverMilli = $availableMilli + $inboundMilli;
            $coverDays = $dailyUsageMilli > 0 ? round($projectedCoverMilli / $dailyUsageMilli, 1) : null;
            $risk = match (true) {
                $uncoveredDemandMilli > 0 => 'shortage',
                $availableMilli <= 0 => 'out_of_stock',
                $availableMilli <= ProcurementQuantity::toMilliUnits((string) $material->minimum_stock_qty) => 'low_stock',
                $coverDays !== null && $coverDays <= 7 => 'running_low',
                default => 'sufficient',
            };

            return [
                'id' => $material->getKey(),
                'code' => $material->code,
                'name' => $material->name,
                'category' => $material->category?->name ?? 'Uncategorized',
                'unit' => $material->unitOfMeasure->code,
                'available' => $stock['available_to_use'],
                'reserved' => $stock['reserved'],
                'minimum' => $material->minimum_stock_qty,
                'reorderTo' => $material->reorder_to_qty,
                'dailyUsage' => ProcurementQuantity::fromMilliUnits($dailyUsageMilli),
                'inbound' => ProcurementQuantity::fromMilliUnits($inboundMilli),
                'approvedDemand' => ProcurementQuantity::fromMilliUnits($approvedDemandMilli),
                'shortageQuantity' => ProcurementQuantity::fromMilliUnits($uncoveredDemandMilli),
                'suggestedReplenishment' => ProcurementQuantity::fromMilliUnits($suggestedMilli),
                'coverDays' => $coverDays,
                'risk' => $risk,
            ];
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function requestData(PurchaseRequest $purchaseRequest): array
    {
        return [
            'id' => $purchaseRequest->getKey(),
            'number' => $purchaseRequest->request_number,
            'requestDate' => $purchaseRequest->request_date?->toDateString(),
            'requiredDate' => $purchaseRequest->required_date?->toDateString(),
            'purpose' => $purchaseRequest->purpose,
            'status' => $purchaseRequest->status->value,
            'requester' => $purchaseRequest->requester?->name,
            'items' => $purchaseRequest->items->map(fn (PurchaseRequestItem $item): array => [
                'id' => $item->getKey(),
                'materialId' => $item->material_id,
                'material' => $item->material->name,
                'code' => $item->material->code,
                'quantity' => $item->requested_qty,
                'unit' => $item->unitOfMeasure->code,
                'justification' => $item->justification,
            ])->values(),
            'approvals' => $purchaseRequest->approvals->map(fn ($approval): array => [
                'decision' => $approval->decision->value,
                'approver' => $approval->approver?->name,
                'comments' => $approval->comments,
                'decidedAt' => $approval->decided_at?->toIso8601String(),
            ])->values(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function orderData(PurchaseOrder $purchaseOrder): array
    {
        return [
            'id' => $purchaseOrder->getKey(),
            'number' => $purchaseOrder->order_number,
            'supplier' => $purchaseOrder->supplier?->name,
            'supplierId' => $purchaseOrder->supplier_id,
            'orderDate' => $purchaseOrder->order_date?->toDateString(),
            'expectedDate' => $purchaseOrder->expected_delivery_date?->toDateString(),
            'currency' => $purchaseOrder->currency_code,
            'status' => $purchaseOrder->status->value,
            'total' => $purchaseOrder->items->sum(fn (PurchaseOrderItem $item): float => (float) $item->line_total),
            'items' => $purchaseOrder->items->map(fn (PurchaseOrderItem $item): array => [
                'id' => $item->getKey(),
                'material' => $item->material->name,
                'code' => $item->material->code,
                'quantity' => $item->ordered_qty,
                'unit' => $item->unitOfMeasure?->code,
                'unitPrice' => $item->unit_price,
            ])->values(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function deliveryData(Delivery $delivery): array
    {
        return [
            'id' => $delivery->getKey(),
            'number' => $delivery->delivery_number,
            'supplier' => $delivery->supplier->name,
            'purchaseOrder' => $delivery->purchaseOrder->order_number,
            'expectedDate' => $delivery->expected_arrival_date?->toDateString(),
            'actualDate' => $delivery->actual_arrival_at?->toDateString(),
            'status' => $delivery->status->value,
            'trackingNumber' => $delivery->tracking_number,
            'items' => $delivery->items->map(fn (DeliveryItem $item): array => [
                'material' => $item->purchaseOrderItem->material->name,
                'dispatchedQuantity' => $item->dispatched_qty,
                'receivedQuantity' => ProcurementQuantity::fromMilliUnits(
                    $item->goodsReceiptItems
                        ->filter(fn (GoodsReceiptItem $receiptItem): bool => $receiptItem->goodsReceipt->status->value === 'posted')
                        ->sum(fn (GoodsReceiptItem $receiptItem): int => $receiptItem->receivedQuantityMilli()),
                ),
                'lots' => $item->goodsReceiptItems
                    ->filter(fn (GoodsReceiptItem $receiptItem): bool => $receiptItem->goodsReceipt->status->value === 'posted')
                    ->map(fn (GoodsReceiptItem $receiptItem): ?string => $receiptItem->inventoryBatch?->lot_code ?? $receiptItem->lot_code)
                    ->filter()
                    ->values(),
                'discrepancies' => $item->goodsReceiptItems
                    ->filter(fn (GoodsReceiptItem $receiptItem): bool => (float) $receiptItem->rejected_qty > 0
                        || filled($receiptItem->discrepancy_notes))
                    ->map(fn (GoodsReceiptItem $receiptItem): array => [
                        'rejectedQuantity' => $receiptItem->rejected_qty,
                        'notes' => $receiptItem->discrepancy_notes,
                    ])
                    ->values(),
            ])->values(),
        ];
    }
}
