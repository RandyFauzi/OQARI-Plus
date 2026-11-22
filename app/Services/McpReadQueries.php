<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\InventoryLocation;
use App\Models\McpConnection;
use App\Models\OutletStockRequest;
use App\Models\PettyPurchase;
use App\Models\RawMaterial;
use App\Models\Shop;
use App\Models\StockCount;
use App\Models\StockTransfer;
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
