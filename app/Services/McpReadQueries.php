<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Branch;
use App\Models\BranchProduct;
use App\Models\CashRegisterSession;
use App\Models\InventoryLocation;
use App\Models\McpConnection;
use App\Models\Order;
use App\Models\OutletStockRequest;
use App\Models\PettyPurchase;
use App\Models\Product;
use App\Models\ProductionWorkOrder;
use App\Models\PurchaseOrder;
use App\Models\RawMaterial;
use App\Models\Shop;
use App\Models\StockCount;
use App\Models\StockMutation;
use App\Models\StockTransfer;
use App\Models\Voucher;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class McpReadQueries
{
    public function __construct(private McpAccess $access, private BranchSalesQuery $sales) {}

    public function context(McpConnection $connection): array
    {
        return [
            'role' => match ($connection->role) {
                'superadmin' => 'platform_admin',
                'manager' => 'outlet_manager',
                default => 'owner',
            },
            'business' => $connection->shop?->name,
            'timezone' => config('attendance.timezone'),
            'read_only' => true,
            'abilities' => array_values(array_intersect($connection->abilities, $this->access->abilities($connection->user, $connection->role))),
            'branches' => $this->branchList($connection)['branches'],
        ];
    }

    public function branchList(McpConnection $connection): array
    {
        return ['branches' => $this->access->branches($connection->user, $connection)->map(fn (Branch $branch) => [
            'branch_id' => $branch->id,
            'name' => $branch->name,
            'slug' => $branch->slug,
        ])->values()->all()];
    }

    public function sales(McpConnection $connection, array $arguments, string $mode): array
    {
        $branches = $this->targetBranches($connection, $arguments, $mode === 'snapshot');
        [$from, $to] = $this->dates($arguments);
        $orders = $this->sales->paidOrders((int) $connection->shop_id, $branches?->modelKeys())
            ->where('created_at', '>=', $from->utc())
            ->where('created_at', '<', $to->addDay()->utc());
        $result = [
            'date_from' => $from->toDateString(),
            'date_to' => $to->toDateString(),
            'timezone' => $from->timezoneName,
            'currency' => 'IDR',
        ];

        if ($mode !== 'products') {
            $metrics = (clone $orders)->selectRaw('COUNT(*) AS paid_orders, SUM(grand_total) AS sales, SUM(subtotal - COALESCE(discount_amount, 0) - COALESCE(total_cogs, 0)) AS gross_profit');
            $result['totals'] = $this->salesTotals((clone $metrics)->firstOrFail(), $connection->role === 'owner');
            if ($mode === 'compare') {
                $byBranch = $metrics->addSelect('branch_id')->groupBy('branch_id')->get()->toBase()->keyBy('branch_id');
                $result['branches'] = $branches?->map(fn (Branch $branch) => [
                    'branch_id' => $branch->id,
                    'name' => $branch->name,
                    ...$this->salesTotals($byBranch->get($branch->id), $connection->role === 'owner'),
                ])->values()->all() ?? [[
                    'branch_id' => null,
                    'name' => $connection->shop->name,
                    ...$result['totals'],
                ]];
            }
        }
        if (in_array($mode, ['snapshot', 'products'], true)) {
            $result['top_products'] = $this->sales->topProducts($orders, (int) ($arguments['limit'] ?? 3))
                ->map(fn (object $row) => ['branch_id' => $row->branch_id, 'name' => $row->product_name, 'units_sold' => (int) $row->units])->all();
        }
        if ($branches?->count() === 1 || $branches === null) {
            $result['branch'] = ['branch_id' => $branches?->first()->id, 'name' => $branches?->first()->name ?? $connection->shop->name];
        }

        return $result;
    }

    public function lowStock(McpConnection $connection, array $arguments): array
    {
        $locations = $this->locations($connection, $arguments)->orderBy('id')->get();
        $pageSize = (int) ($arguments['limit'] ?? 20);
        $balances = RawMaterial::where('raw_materials.shop_id', $connection->shop_id)
            ->join('inventory_locations as loc', fn ($join) => $join->on('loc.shop_id', '=', 'raw_materials.shop_id')->whereIn('loc.id', $locations->modelKeys()))
            ->leftJoin('inventory_balances as balance', fn ($join) => $join->on('balance.inventory_location_id', '=', 'loc.id')->on('balance.raw_material_id', '=', 'raw_materials.id'))
            ->whereRaw("COALESCE(balance.quantity, CASE WHEN loc.code = 'outlet' THEN raw_materials.current_stock ELSE 0 END) < raw_materials.minimum_stock")
            ->select('raw_materials.id', 'raw_materials.name', 'raw_materials.unit', 'raw_materials.minimum_stock', 'loc.id as location_id', 'loc.name as location_name')
            ->selectRaw("COALESCE(balance.quantity, CASE WHEN loc.code = 'outlet' THEN raw_materials.current_stock ELSE 0 END) as quantity")
            ->withCasts(['quantity' => 'decimal:6'])
            ->orderBy('loc.id')->orderBy('raw_materials.id')->simplePaginate($pageSize, ['*'], 'page', (int) ($arguments['page'] ?? 1));

        return ['items' => collect($balances->items())->map(fn (RawMaterial $row) => [
            'material_id' => $row->id, 'name' => $row->name, 'unit' => $row->unit,
            'quantity' => (string) $row->quantity, 'minimum_stock' => (string) $row->minimum_stock,
            'location_id' => $row->location_id, 'location_name' => $row->location_name,
        ])->all(), 'next_page' => $balances->hasMorePages() ? $balances->currentPage() + 1 : null];
    }

    public function transfers(McpConnection $connection, array $arguments): array
    {
        $locationIds = $this->locations($connection, $arguments)->pluck('id');
        $rows = StockTransfer::where('shop_id', $connection->shop_id)
            ->where(fn ($query) => $query->whereIn('to_location_id', $locationIds)->orWhereIn('from_location_id', $locationIds))
            ->with(['fromLocation:id,shop_id,name', 'toLocation:id,shop_id,name', 'rawMaterial:id,shop_id,name,unit'])
            ->latest('id')->simplePaginate((int) ($arguments['limit'] ?? 20), ['*'], 'page', (int) ($arguments['page'] ?? 1));

        return ['items' => collect($rows->items())->map(fn (StockTransfer $row) => [
            'transfer_id' => $row->id, 'material' => $row->rawMaterial?->name, 'unit' => $row->rawMaterial?->unit,
            'from' => $row->fromLocation?->name, 'to' => $row->toLocation?->name,
            'sent' => $row->sent_quantity, 'received' => $row->received_quantity, 'outstanding' => $row->remainingQuantity(),
            'created_at' => $row->created_at?->toIso8601String(),
        ])->all(), 'next_page' => $rows->hasMorePages() ? $rows->currentPage() + 1 : null];
    }

    public function operations(McpConnection $connection, array $arguments): array
    {
        $branchIds = $this->targetBranches($connection, $arguments)?->modelKeys();
        [$from, $to] = $this->dates($arguments);
        $counts = [];
        foreach (['stock_requests' => OutletStockRequest::class, 'stock_counts' => StockCount::class, 'petty_purchases' => PettyPurchase::class] as $name => $model) {
            $counts[$name] = (object) $model::withoutGlobalScope('branch')->where('shop_id', $connection->shop_id)
                ->when($branchIds !== null, fn (Builder $query) => $query->whereIn('branch_id', $branchIds))
                ->where('created_at', '>=', $from->utc())->where('created_at', '<', $to->addDay()->utc())
                ->selectRaw('status, COUNT(*) AS total')->groupBy('status')->orderBy('status')->toBase()->pluck('total', 'status')->map(fn ($count): int => (int) $count)->all();
        }

        return ['date_from' => $from->toDateString(), 'date_to' => $to->toDateString(), 'counts' => $counts];
    }

    public function platform(): array
    {
        return ['businesses' => (object) Shop::selectRaw('status, COUNT(*) AS total')->groupBy('status')
            ->orderBy('status')->toBase()->pluck('total', 'status')->map(fn ($count): int => (int) $count)->all()];
    }

    public function catalogProducts(McpConnection $connection, array $arguments): array
    {
        $branches = $this->targetBranches($connection, $arguments);
        $singleBranchId = $branches?->count() === 1 ? $branches->first()->id : null;
        $search = isset($arguments['search']) ? trim((string) $arguments['search']) : '';
        $pageSize = (int) ($arguments['limit'] ?? 20);
        $page = (int) ($arguments['page'] ?? 1);

        $query = Product::where('shop_id', $connection->shop_id)
            ->when($search !== '', function (Builder $q) use ($search): void {
                $like = '%'.$search.'%';
                $q->where(fn (Builder $sub) => $sub->where('name', 'like', $like)->orWhere('category_name', 'like', $like));
            })
            ->with(['category:id,name']);

        $totalProducts = (clone $query)->count();
        $rows = $query->orderBy('name')->simplePaginate($pageSize, ['*'], 'page', $page);

        $overrides = $singleBranchId !== null
            ? BranchProduct::where('branch_id', $singleBranchId)
                ->whereIn('product_id', collect($rows->items())->pluck('id'))
                ->get()->keyBy('product_id')
            : collect();

        return [
            'total_products' => $totalProducts,
            'items' => collect($rows->items())->map(function (Product $product) use ($overrides): array {
                $override = $overrides->get($product->id);
                $price = $override?->price_override !== null ? (float) $override->price_override : (float) $product->price;
                $discountPrice = $override?->discount_price_override !== null
                    ? (float) $override->discount_price_override
                    : ($product->discount_price !== null ? (float) $product->discount_price : null);
                $isDiscountActive = $override?->is_discount_active_override !== null
                    ? (bool) $override->is_discount_active_override
                    : (bool) $product->is_discount_active;

                return [
                    'product_id' => $product->id,
                    'name' => $product->name,
                    'category' => $product->category?->name ?? $product->category_name,
                    'price' => $this->money($price),
                    'discount_price' => $discountPrice !== null ? $this->money($discountPrice) : null,
                    'is_discount_active' => $isDiscountActive,
                    'is_best_seller' => (bool) $product->is_best_seller,
                    'is_sold_out' => $override ? (bool) $override->is_sold_out : (bool) $product->is_sold_out,
                    'is_available' => $override ? (bool) $override->is_available : true,
                ];
            })->all(),
            'next_page' => $rows->hasMorePages() ? $rows->currentPage() + 1 : null,
        ];
    }

    public function salesBreakdown(McpConnection $connection, array $arguments): array
    {
        $branches = $this->targetBranches($connection, $arguments);
        [$from, $to] = $this->dates($arguments);
        $zone = $from->timezoneName;
        $orders = $this->sales->paidOrders((int) $connection->shop_id, $branches?->modelKeys())
            ->where('created_at', '>=', $from->utc())
            ->where('created_at', '<', $to->addDay()->utc());

        $byPayment = (clone $orders)
            ->selectRaw("COALESCE(payment_method, 'LAINNYA') AS label, COUNT(*) AS paid_orders, SUM(grand_total) AS sales")
            ->groupBy('label')->orderByDesc('sales')->toBase()->get()
            ->map(fn (object $r) => [
                'payment_method' => (string) $r->label,
                'paid_orders' => (int) $r->paid_orders,
                'net_sales' => $this->money((float) $r->sales),
            ])->all();

        $byFulfillment = (clone $orders)
            ->selectRaw("COALESCE(fulfillment_type, 'DINE_IN') AS label, COUNT(*) AS paid_orders, SUM(grand_total) AS sales")
            ->groupBy('label')->orderByDesc('sales')->toBase()->get()
            ->map(fn (object $r) => [
                'fulfillment_type' => (string) $r->label,
                'paid_orders' => (int) $r->paid_orders,
                'net_sales' => $this->money((float) $r->sales),
            ])->all();

        $dayBuckets = [];
        $hourBuckets = [];
        foreach ((clone $orders)->select('created_at', 'grand_total')->toBase()->cursor() as $row) {
            $local = CarbonImmutable::parse($row->created_at, 'UTC')->setTimezone($zone);
            $dayKey = $local->toDateString();
            $hourKey = (int) $local->hour;
            $amount = (float) $row->grand_total;

            $dayBuckets[$dayKey]['paid_orders'] = ($dayBuckets[$dayKey]['paid_orders'] ?? 0) + 1;
            $dayBuckets[$dayKey]['sales'] = ($dayBuckets[$dayKey]['sales'] ?? 0.0) + $amount;

            $hourBuckets[$hourKey]['paid_orders'] = ($hourBuckets[$hourKey]['paid_orders'] ?? 0) + 1;
            $hourBuckets[$hourKey]['sales'] = ($hourBuckets[$hourKey]['sales'] ?? 0.0) + $amount;
        }
        ksort($dayBuckets);
        $byDay = collect($dayBuckets)->map(fn (array $b, string $date) => [
            'date' => $date,
            'paid_orders' => $b['paid_orders'],
            'net_sales' => $this->money($b['sales']),
        ])->values()->all();

        $peakHours = collect($hourBuckets)->map(fn (array $b, int $hour) => [
            'hour' => $hour,
            'paid_orders' => $b['paid_orders'],
            'net_sales' => $this->money($b['sales']),
            '_sort' => $b['sales'],
        ])->sortByDesc('_sort')->take(5)->map(fn (array $b) => [
            'hour' => $b['hour'],
            'paid_orders' => $b['paid_orders'],
            'net_sales' => $b['net_sales'],
        ])->values()->all();

        return [
            'date_from' => $from->toDateString(),
            'date_to' => $to->toDateString(),
            'timezone' => $zone,
            'currency' => 'IDR',
            'by_payment_method' => $byPayment,
            'by_fulfillment_type' => $byFulfillment,
            'by_day' => $byDay,
            'peak_hours' => $peakHours,
        ];
    }

    public function orders(McpConnection $connection, array $arguments): array
    {
        $branchIds = $this->targetBranches($connection, $arguments)?->modelKeys();
        [$from, $to] = $this->dates($arguments);
        $pageSize = (int) ($arguments['limit'] ?? 20);
        $page = (int) ($arguments['page'] ?? 1);

        $rows = Order::withoutGlobalScope('branch')
            ->where('shop_id', $connection->shop_id)
            ->when($branchIds !== null, fn (Builder $q) => $q->whereIn('branch_id', $branchIds))
            ->when(isset($arguments['payment_status']), fn (Builder $q) => $q->where('payment_status', $arguments['payment_status']))
            ->where('created_at', '>=', $from->utc())
            ->where('created_at', '<', $to->addDay()->utc())
            ->with(['branch:id,name'])
            ->withCount('items')
            ->latest('id')
            ->simplePaginate($pageSize, ['*'], 'page', $page);

        return [
            'items' => collect($rows->items())->map(fn (Order $order) => [
                'order_id' => $order->id,
                'branch_id' => $order->branch_id,
                'branch_name' => $order->branch?->name,
                'payment_method' => $order->payment_method,
                'order_status' => $order->order_status,
                'payment_status' => $order->payment_status,
                'fulfillment_type' => $order->fulfillment_type,
                'subtotal' => $this->money((float) $order->subtotal),
                'discount_amount' => $this->money((float) ($order->discount_amount ?? 0)),
                'grand_total' => $this->money((float) $order->grand_total),
                'voucher_code' => $order->voucher_code,
                'items_count' => (int) $order->items_count,
                'created_at' => $order->created_at?->toIso8601String(),
            ])->all(),
            'next_page' => $rows->hasMorePages() ? $rows->currentPage() + 1 : null,
        ];
    }

    public function vouchers(McpConnection $connection, array $arguments): array
    {
        $branchIds = $this->targetBranches($connection, $arguments)?->modelKeys();
        $pageSize = (int) ($arguments['limit'] ?? 20);
        $page = (int) ($arguments['page'] ?? 1);

        $rows = Voucher::where('shop_id', $connection->shop_id)
            ->when($branchIds !== null, fn (Builder $q) => $q->where(fn (Builder $sub) => $sub->whereNull('branch_id')->orWhereIn('branch_id', $branchIds)))
            ->latest('id')
            ->simplePaginate($pageSize, ['*'], 'page', $page);

        return [
            'items' => collect($rows->items())->map(fn (Voucher $voucher) => [
                'voucher_id' => $voucher->id,
                'code' => $voucher->code,
                'name' => $voucher->name,
                'discount_type' => (string) $voucher->discount_type,
                'discount_value' => $this->money((float) $voucher->discount_value),
                'min_spend' => $voucher->min_spend !== null ? $this->money((float) $voucher->min_spend) : null,
                'max_discount' => $voucher->max_discount !== null ? $this->money((float) $voucher->max_discount) : null,
                'usage_limit' => $voucher->usage_limit,
                'used_count' => (int) ($voucher->used_count ?? 0),
                'is_active' => (bool) $voucher->is_active,
                'expires_at' => $voucher->expires_at?->toIso8601String(),
            ])->all(),
            'next_page' => $rows->hasMorePages() ? $rows->currentPage() + 1 : null,
        ];
    }

    public function rawMaterials(McpConnection $connection, array $arguments): array
    {
        $locations = $this->locations($connection, $arguments)->orderBy('id')->get();
        $search = isset($arguments['search']) ? trim((string) $arguments['search']) : '';
        $pageSize = (int) ($arguments['limit'] ?? 20);
        $page = (int) ($arguments['page'] ?? 1);

        $baseQuery = RawMaterial::where('raw_materials.shop_id', $connection->shop_id)
            ->when($search !== '', function (Builder $q) use ($search): void {
                $like = '%'.$search.'%';
                $q->where(fn (Builder $sub) => $sub->where('raw_materials.name', 'like', $like)->orWhere('raw_materials.sku', 'like', $like));
            });

        $totalMaterials = (clone $baseQuery)->count();
        $balances = $baseQuery
            ->join('inventory_locations as loc', fn ($join) => $join->on('loc.shop_id', '=', 'raw_materials.shop_id')->whereIn('loc.id', $locations->modelKeys()))
            ->leftJoin('inventory_balances as balance', fn ($join) => $join->on('balance.inventory_location_id', '=', 'loc.id')->on('balance.raw_material_id', '=', 'raw_materials.id'))
            ->select('raw_materials.id', 'raw_materials.name', 'raw_materials.sku', 'raw_materials.unit', 'raw_materials.minimum_stock', 'raw_materials.cost_per_unit', 'loc.id as location_id', 'loc.name as location_name')
            ->selectRaw("COALESCE(balance.quantity, CASE WHEN loc.code = 'outlet' THEN raw_materials.current_stock ELSE 0 END) as quantity")
            ->withCasts(['quantity' => 'decimal:6'])
            ->orderBy('loc.id')->orderBy('raw_materials.name')
            ->simplePaginate($pageSize, ['*'], 'page', $page);

        return [
            'total_materials' => $totalMaterials,
            'items' => collect($balances->items())->map(fn (RawMaterial $row) => [
                'material_id' => $row->id,
                'name' => $row->name,
                'sku' => $row->sku,
                'unit' => $row->unit,
                'quantity' => (string) $row->quantity,
                'minimum_stock' => (string) $row->minimum_stock,
                'cost_per_unit' => $row->cost_per_unit !== null ? (string) $row->cost_per_unit : null,
                'location_id' => $row->location_id,
                'location_name' => $row->location_name,
                'is_low_stock' => (float) $row->quantity < (float) $row->minimum_stock,
            ])->all(),
            'next_page' => $balances->hasMorePages() ? $balances->currentPage() + 1 : null,
        ];
    }

    public function stockMutations(McpConnection $connection, array $arguments): array
    {
        $locationIds = $this->locations($connection, $arguments)->pluck('id');
        [$from, $to] = $this->dates($arguments);
        $pageSize = (int) ($arguments['limit'] ?? 20);
        $page = (int) ($arguments['page'] ?? 1);

        $rows = StockMutation::where('shop_id', $connection->shop_id)
            ->whereIn('inventory_location_id', $locationIds)
            ->where('created_at', '>=', $from->utc())
            ->where('created_at', '<', $to->addDay()->utc())
            ->with(['rawMaterial:id,name,unit', 'location:id,name'])
            ->latest('id')
            ->simplePaginate($pageSize, ['*'], 'page', $page);

        return [
            'items' => collect($rows->items())->map(fn (StockMutation $row) => [
                'mutation_id' => $row->id,
                'material' => $row->rawMaterial?->name,
                'unit' => $row->unit ?? $row->rawMaterial?->unit,
                'type' => (string) $row->type,
                'amount' => (string) $row->amount,
                'balance_after' => $row->balance_after !== null ? (string) $row->balance_after : null,
                'location_name' => $row->location?->name,
                'notes' => $row->notes,
                'created_at' => $row->created_at?->toIso8601String(),
            ])->all(),
            'next_page' => $rows->hasMorePages() ? $rows->currentPage() + 1 : null,
        ];
    }

    public function purchaseOrders(McpConnection $connection, array $arguments): array
    {
        [$from, $to] = $this->dates($arguments);
        $pageSize = (int) ($arguments['limit'] ?? 20);
        $page = (int) ($arguments['page'] ?? 1);

        $rows = PurchaseOrder::where('shop_id', $connection->shop_id)
            ->where('created_at', '>=', $from->utc())
            ->where('created_at', '<', $to->addDay()->utc())
            ->with(['supplier:id,name'])
            ->withCount('items')
            ->latest('id')
            ->simplePaginate($pageSize, ['*'], 'page', $page);

        return [
            'items' => collect($rows->items())->map(fn (PurchaseOrder $po) => [
                'po_id' => $po->id,
                'supplier' => $po->supplier?->name,
                'status' => (string) $po->status,
                'items_count' => (int) $po->items_count,
                'notes' => $po->notes,
                'approved_at' => $po->approved_at?->toIso8601String(),
                'created_at' => $po->created_at?->toIso8601String(),
            ])->all(),
            'next_page' => $rows->hasMorePages() ? $rows->currentPage() + 1 : null,
        ];
    }

    public function cashSessions(McpConnection $connection, array $arguments): array
    {
        $branchIds = $this->targetBranches($connection, $arguments)?->modelKeys();
        [$from, $to] = $this->dates($arguments);
        $pageSize = (int) ($arguments['limit'] ?? 20);
        $page = (int) ($arguments['page'] ?? 1);

        $rows = CashRegisterSession::whereHas('register', function (Builder $q) use ($connection, $branchIds): void {
            $q->withoutGlobalScope('branch')->where('shop_id', $connection->shop_id)
                ->when($branchIds !== null, fn (Builder $b) => $b->whereIn('branch_id', $branchIds));
        })
            ->where('opened_at', '>=', $from->utc())
            ->where('opened_at', '<', $to->addDay()->utc())
            ->with(['register:id,name', 'user:id,name'])
            ->latest('id')
            ->simplePaginate($pageSize, ['*'], 'page', $page);

        return [
            'items' => collect($rows->items())->map(fn (CashRegisterSession $session) => [
                'session_id' => $session->id,
                'register_name' => $session->register?->name,
                'cashier' => $session->user?->name,
                'status' => (string) $session->status,
                'reconciliation_status' => $session->reconciliation_status,
                'opening_cash' => $this->money((float) $session->opening_cash),
                'expected_cash' => $session->expected_cash !== null ? $this->money((float) $session->expected_cash) : null,
                'actual_cash' => $session->actual_cash !== null ? $this->money((float) $session->actual_cash) : null,
                'difference' => $session->difference !== null ? $this->money((float) $session->difference) : null,
                'total_cash_sales' => $session->total_cash_sales !== null ? $this->money((float) $session->total_cash_sales) : null,
                'total_qris_sales' => $session->total_qris_sales !== null ? $this->money((float) $session->total_qris_sales) : null,
                'total_other_sales' => $session->total_other_sales !== null ? $this->money((float) $session->total_other_sales) : null,
                'opened_at' => $session->opened_at?->toIso8601String(),
                'closed_at' => $session->closed_at?->toIso8601String(),
            ])->all(),
            'next_page' => $rows->hasMorePages() ? $rows->currentPage() + 1 : null,
        ];
    }

    public function attendance(McpConnection $connection, array $arguments): array
    {
        $branchIds = $this->targetBranches($connection, $arguments)?->modelKeys();
        [$from, $to] = $this->dates($arguments);

        $summary = Attendance::withoutGlobalScope('branch')
            ->where('shop_id', $connection->shop_id)
            ->when($branchIds !== null, fn (Builder $q) => $q->whereIn('branch_id', $branchIds))
            ->whereBetween('work_date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('COUNT(*) AS total_records, SUM(CASE WHEN checked_in_at IS NOT NULL THEN 1 ELSE 0 END) AS checked_in, SUM(CASE WHEN checked_out_at IS NOT NULL THEN 1 ELSE 0 END) AS checked_out, SUM(CASE WHEN COALESCE(late_minutes, 0) > 0 THEN 1 ELSE 0 END) AS late_count, COALESCE(SUM(late_minutes), 0) AS total_late_minutes, COALESCE(SUM(worked_minutes), 0) AS total_worked_minutes')
            ->toBase()
            ->first();

        return [
            'date_from' => $from->toDateString(),
            'date_to' => $to->toDateString(),
            'total_records' => (int) ($summary?->total_records ?? 0),
            'checked_in' => (int) ($summary?->checked_in ?? 0),
            'checked_out' => (int) ($summary?->checked_out ?? 0),
            'late_count' => (int) ($summary?->late_count ?? 0),
            'total_late_minutes' => (int) ($summary?->total_late_minutes ?? 0),
            'total_worked_minutes' => (int) ($summary?->total_worked_minutes ?? 0),
        ];
    }

    public function productionOrders(McpConnection $connection, array $arguments): array
    {
        $locationIds = $this->locations($connection, $arguments)->pluck('id');
        [$from, $to] = $this->dates($arguments);
        $pageSize = (int) ($arguments['limit'] ?? 20);
        $page = (int) ($arguments['page'] ?? 1);

        $rows = ProductionWorkOrder::where('shop_id', $connection->shop_id)
            ->whereIn('inventory_location_id', $locationIds)
            ->where('created_at', '>=', $from->utc())
            ->where('created_at', '<', $to->addDay()->utc())
            ->with(['recipe:id,name', 'location:id,name'])
            ->latest('id')
            ->simplePaginate($pageSize, ['*'], 'page', $page);

        return [
            'items' => collect($rows->items())->map(fn (ProductionWorkOrder $wo) => [
                'work_order_id' => $wo->id,
                'recipe' => $wo->recipe?->name,
                'location_name' => $wo->location?->name,
                'status' => (string) $wo->status,
                'target_quantity' => (string) $wo->target_quantity,
                'good_quantity' => $wo->good_quantity !== null ? (string) $wo->good_quantity : null,
                'waste_quantity' => $wo->waste_quantity !== null ? (string) $wo->waste_quantity : null,
                'total_cost' => $wo->total_cost !== null ? (string) $wo->total_cost : null,
                'created_at' => $wo->created_at?->toIso8601String(),
            ])->all(),
            'next_page' => $rows->hasMorePages() ? $rows->currentPage() + 1 : null,
        ];
    }

    private function money(float $amount): string
    {
        return number_format($amount, 2, '.', '');
    }

    private function salesTotals(?object $metrics, bool $showProfit): array
    {
        $orders = (int) ($metrics?->paid_orders ?? 0);
        $sales = (float) ($metrics?->sales ?? 0);

        return [
            'net_sales' => number_format($sales, 2, '.', ''),
            'paid_orders' => $orders,
            'average_order_value' => number_format($orders ? $sales / $orders : 0, 2, '.', ''),
            ...($showProfit ? ['gross_profit' => number_format((float) ($metrics?->gross_profit ?? 0), 2, '.', '')] : []),
        ];
    }

    /** @return array{CarbonImmutable, CarbonImmutable} */
    private function dates(array $arguments): array
    {
        $zone = config('attendance.timezone');
        $today = CarbonImmutable::today($zone);
        if (isset($arguments['date_from'], $arguments['date_to'])) {
            $from = CarbonImmutable::parse($arguments['date_from'], $zone)->startOfDay();
            $to = CarbonImmutable::parse($arguments['date_to'], $zone)->startOfDay();
        } else {
            $period = $arguments['period'] ?? 'today';
            [$from, $to] = match ($period) {
                'yesterday' => [$today->subDay(), $today->subDay()],
                'last_7_days' => [$today->subDays(6), $today],
                'last_30_days' => [$today->subDays(29), $today],
                default => [$today, $today],
            };
        }
        if ($from->gt($to) || $to->gt($today) || $to->gte($from->addDays(config('oqari_mcp.max_date_range_days')))) {
            throw ValidationException::withMessages(['date_from' => 'Rentang tanggal tidak valid atau melebihi batas laporan.']);
        }

        return [$from, $to];
    }

    private function locations(McpConnection $connection, array $arguments): Builder
    {
        $branches = $this->targetBranches($connection, $arguments);
        $locations = InventoryLocation::where('shop_id', $connection->shop_id);
        if ($branches === null) {
            return $locations->when($connection->role !== 'owner', fn (Builder $query) => $query->where('code', InventoryLocation::Outlet));
        }

        return $locations->whereIn('branch_id', $branches->modelKeys());
    }

    /** @return Collection<int, Branch>|null */
    private function targetBranches(McpConnection $connection, array $arguments, bool $single = false): ?Collection
    {
        $name = isset($arguments['branch_name']) ? Str::lower(trim($arguments['branch_name'])) : null;
        $id = isset($arguments['branch_id']) ? (int) $arguments['branch_id'] : null;
        if (! $connection->shop->branches_enabled) {
            if ($id !== null || ($name !== null && ! in_array($name, [Str::lower($connection->shop->name), 'outlet utama'], true))) {
                throw new AuthorizationException('Cabang tidak tersedia untuk koneksi ini.');
            }

            return null;
        }

        $branches = $this->access->branches($connection->user, $connection);
        if ($id !== null || $name !== null) {
            $branches = $branches->filter(fn (Branch $branch) => ($id === null || (int) $branch->id === $id)
                && ($name === null || in_array($name, [Str::lower($branch->name), Str::lower($branch->slug)], true)));
            if ($branches->count() !== 1) {
                throw new AuthorizationException('Cabang tidak tersedia atau namanya ambigu. Gunakan oqari_list_branches untuk memilih ID.');
            }
        }
        if ($single && $branches->count() !== 1) {
            throw ValidationException::withMessages(['branch_id' => 'Pilih satu cabang dari oqari_list_branches.']);
        }

        return $branches;
    }
}
