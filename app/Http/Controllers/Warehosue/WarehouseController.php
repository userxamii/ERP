<?php

namespace App\Http\Controllers\Warehouse;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\WarehouseTask; // Assuming you have or will create this model
use App\Models\InventoryItem; // Assuming you have or will create this model
use Inertia\Inertia;

class WarehouseController extends Controller
{
    /**
     * Display the warehouse staff dashboard with live metrics and task queue.
     */
    public function index(Request $request)
    {
        $search = $request->input('search');
        $tab = $request->input('tab', 'picking'); // default tab

        // 1. Fetch KPI Metrics for Dashboard Header Cards
        $stats = [
            'pending_picks' => WarehouseTask::where('type', 'Picking')->where('status', 'Pending')->count(),
            'incoming_shipments' => WarehouseTask::where('type', 'Receiving')->where('status', 'Pending')->count(),
            'low_stock_items' => InventoryItem::whereColumn('stock_qty', '<=', 'reorder_level')->count(),
            'packed_ready' => WarehouseTask::where('type', 'Packing')->where('status', 'Ready')->count(),
        ];

        // 2. Fetch Tasks Query with Search & Tab Filtering
        $query = WarehouseTask::query();

        if ($tab !== 'all') {
            $query->where('type', 'LIKE', "%{$tab}%");
        }

        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('task_id', 'LIKE', "%{$search}%")
                  ->orWhere('item_sku', 'LIKE', "%{$search}%")
                  ->orWhere('zone', 'LIKE', "%{$search}%");
            });
        }

        $tasks = $query->orderBy('created_at', 'desc')->paginate(10);

        // 3. Return Inertia response rendering your React Dashboard component
        return Inertia::render('Warehouse/Dashboard', [
            'stats' => $stats,
            'tasks' => $tasks,
            'filters' => [
                'search' => $search,
                'tab' => $tab,
            ]
        ]);
    }

    /**
     * Process/Update a warehouse task status (e.g., mark as 'In Progress' or 'Completed')
     */
    public function updateTaskStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|string|in:Pending,In Progress,Ready,Completed',
        ]);

        $task = WarehouseTask::findOrFail($id);
        $task->status = $request->status;
        $task->save();

        return redirect()->back()->with('success', 'Task status updated successfully.');
    }
}