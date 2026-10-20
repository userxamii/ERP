use Illuminate\Support\Facades\Route;

Route::get('/dashboard', function () {
    $inventory = [
        [
            'name' => 'Chocolate Drink',
            'batch' => 'CH-1001',
            'quantity' => 250,
            'expiry' => now()->addDays(120)->toDateString(),
        ],
        [
            'name' => 'Coffee Mix',
            'batch' => 'CF-1002',
            'quantity' => 120,
            'expiry' => now()->addDays(20)->toDateString(),
        ],
        [
            'name' => 'Orange Juice',
            'batch' => 'OJ-1003',
            'quantity' => 80,
            'expiry' => now()->addDays(5)->toDateString(),
        ],
        [
            'name' => 'Milk Powder',
            'batch' => 'MP-1004',
            'quantity' => 40,
            'expiry' => now()->subDays(3)->toDateString(),
        ],
        [
            'name' => 'Biscuit Pack',
            'batch' => 'BP-1005',
            'quantity' => 300,
            'expiry' => now()->addDays(75)->toDateString(),
        ],
    ];

    foreach ($inventory as &$item) {
        $days = now()->startOfDay()->diffInDays(
            \Carbon\Carbon::parse($item['expiry'])->startOfDay(),
            false
        );

        $item['days_left'] = $days;

        if ($days < 0) {
            $item['status'] = 'Expired';
        } elseif ($days <= 30) {
            $item['status'] = 'Near Expiry';
        } else {
            $item['status'] = 'Good';
        }
    }
    unset($item);

    return view('dashboard', [
        'inventory' => $inventory,
        'totalItems' => count($inventory),
        'totalStock' => array_sum(array_column($inventory, 'quantity')),
        'nearExpiry' => count(array_filter(
            $inventory,
            fn ($item) => $item['status'] === 'Near Expiry'
        )),
        'expired' => count(array_filter(
            $inventory,
            fn ($item) => $item['status'] === 'Expired'
        )),
    ]);
});