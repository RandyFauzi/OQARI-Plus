<?php

namespace App\Actions\Branch;

use App\Models\Branch;
use App\Models\OutletStockRequest;
use App\Models\PettyPurchase;
use App\Models\PurchaseOrder;
use App\Models\RawMaterial;
use App\Models\StockCount;
use App\Models\StockTransfer;
use App\Models\User;
use App\Services\BranchSalesQuery;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BuildOwnerDashboard
{
    public function __construct(private BranchSalesQuery $salesQuery) {}

    /**
     * @param  Collection<int, Branch>  $branches  Active branches belonging to the owner's business.
     * @return array<string, mixed>
     */
    public function execute(User $owner, Collection $branches, int $days, ?Branch $focus): array
    {
        $timezone = config('attendance.timezone');
        $today = CarbonImmutable::today($timezone);
        $through = $today->addDay();
        $from = $through->subDays($days);
        $dates = collect(range(0, $days - 1))->map(fn (int $offset) => $from->addDays($offset));
        $focusedBranches = $focus ? $branches->where('id', $focus->id) : $branches;
        $focusedIds = $focusedBranches->modelKeys();

        $paid = $this->salesQuery->paidOrders($owner->shop_id, $branches->modelKeys());
        $current = (clone $paid)
            ->where('created_at', '>=', $from->utc())
            ->where('created_at', '<', $through->utc());

        $summary = (clone $current)->select('branch_id')
            ->selectRaw('COUNT(*) AS orders, SUM(grand_total) AS sales')
            ->selectRaw('SUM(subtotal - COALESCE(discount_amount, 0) - COALESCE(total_cogs, 0)) AS gross_profit');
        foreach ($dates as $index => $date) {
            $summary->selectRaw(
                "SUM(CASE WHEN created_at >= ? AND created_at < ? THEN grand_total ELSE 0 END) AS day_$index",
                [$date->utc(), $date->addDay()->utc()],
            );
        }

        $results = $summary->groupBy('branch_id')->get()->toBase()->keyBy('branch_id');
        $branchStats = $branches->toBase()->mapWithKeys(function (Branch $branch) use ($results): array {
            $sales = (float) ($results->get($branch->id)?->sales ?? 0);
            $grossProfit = (float) ($results->get($branch->id)?->gross_profit ?? 0);

            return [
                $branch->id => [
                    'sales' => $sales,
                    'orders' => (int) ($results->get($branch->id)?->orders ?? 0),
                    'grossProfit' => $grossProfit,
                    'marginPct' => $sales > 0 ? round(($grossProfit / $sales) * 100, 1) : 0.0,
                ],
            ];
        });
        $focusedStats = $branchStats->only($focusedIds);

        $sales = $focusedStats->sum('sales');
        $orders = $focusedStats->sum('orders');
        $previousSales = (float) (clone $paid)->whereIn('branch_id', $focusedIds)
            ->where('created_at', '>=', $from->subDays($days)->utc())
            ->where('created_at', '<', $from->utc())
            ->sum('grand_total');
        $focusedResults = $results->only($focusedIds);
        $trend = $dates->map(fn (CarbonImmutable $date, int $index) => [
            'date' => $date,
            'sales' => (float) $focusedResults->sum('day_'.$index),
        ]);

        $topProducts = $this->salesQuery->topProducts((clone $current)->whereIn('branch_id', $focusedIds), 3)
            ->groupBy('branch_id');

        $focusedOrdersQuery = (clone $current)->whereIn('branch_id', $focusedIds);

        $paymentRows = (clone $focusedOrdersQuery)
            ->selectRaw("COALESCE(NULLIF(UPPER(payment_method), ''), 'OTHER') as method, COUNT(*) as orders, SUM(grand_total) as sales")
            ->groupBy('method')
            ->get();

        $paymentBreakdown = $paymentRows->map(function ($row) use ($sales): array {
            $method = strtoupper((string) $row->method);
            $label = match ($method) {
                'CASH', 'TUNAI' => 'Tunai (Cash)',
                'QRIS' => 'QRIS',
                'TRANSFER', 'BANK_TRANSFER' => 'Transfer Bank',
                'CARD', 'DEBIT', 'CREDIT', 'EDC' => 'Kartu / EDC',
                default => $method === 'OTHER' ? 'Lainnya' : $method,
            };

            return [
                'key' => $method,
                'label' => $label,
                'orders' => (int) $row->orders,
                'sales' => (float) $row->sales,
                'pct' => $sales > 0 ? round(((float) $row->sales / $sales) * 100, 1) : 0.0,
            ];
        })->sortByDesc('sales')->values();

        $channelRows = (clone $focusedOrdersQuery)
            ->selectRaw("COALESCE(NULLIF(UPPER(fulfillment_type), ''), 'DINE_IN') as channel, COUNT(*) as orders, SUM(grand_total) as sales")
            ->groupBy('channel')
            ->get();

        $channelBreakdown = $channelRows->map(function ($row) use ($orders): array {
            $channel = strtoupper((string) $row->channel);
            $label = match ($channel) {
                'DINE_IN' => 'Dine-In',
                'TAKEAWAY' => 'Takeaway',
                'DELIVERY' => 'Delivery',
                default => $channel,
            };

            return [
                'key' => $channel,
                'label' => $label,
                'orders' => (int) $row->orders,
                'sales' => (float) $row->sales,
                'pct' => $orders > 0 ? round(((int) $row->orders / $orders) * 100, 1) : 0.0,
            ];
        })->sortByDesc('orders')->values();

        $hourExpression = match (DB::connection()->getDriverName()) {
            'sqlite' => "CAST(strftime('%H', created_at) AS INTEGER)",
            'pgsql' => 'EXTRACT(HOUR FROM created_at)',
            default => 'HOUR(created_at)',
        };
        $hourlyRaw = (clone $focusedOrdersQuery)
            ->selectRaw("{$hourExpression} as hour, COUNT(*) as orders, SUM(grand_total) as sales")
            ->groupBy(DB::raw($hourExpression))
            ->get()
            ->keyBy(fn ($row) => (int) $row->hour);

        $peakHours = collect(range(8, 22))->map(function (int $hour) use ($hourlyRaw): array {
            $row = $hourlyRaw->get($hour);

            return [
                'hour' => $hour,
                'label' => str_pad((string) $hour, 2, '0', STR_PAD_LEFT).':00',
                'orders' => (int) ($row?->orders ?? 0),
                'sales' => (float) ($row?->sales ?? 0),
            ];
        });

        $actionCenter = [
            'pendingPo' => Schema::hasTable('purchase_orders')
                ? PurchaseOrder::where('shop_id', $owner->shop_id)->where('status', PurchaseOrder::Draft)->count()
                : 0,
            'pendingRequests' => Schema::hasTable('outlet_stock_requests')
                ? OutletStockRequest::where('shop_id', $owner->shop_id)->where('status', 'PENDING')->count()
                : 0,
            'pendingCounts' => Schema::hasTable('stock_counts')
                ? StockCount::where('shop_id', $owner->shop_id)->where('status', 'PENDING')->count()
                : 0,
            'pendingPetty' => Schema::hasTable('petty_purchases')
                ? PettyPurchase::where('shop_id', $owner->shop_id)->where('status', 'PENDING')->count()
                : 0,
            'pendingTransfers' => Schema::hasTable('stock_transfers')
                ? StockTransfer::where('shop_id', $owner->shop_id)->whereColumn('sent_quantity', '>', 'received_quantity')->count()
                : 0,
            'lowStock' => Schema::hasTable('raw_materials')
                ? RawMaterial::where('shop_id', $owner->shop_id)->where('minimum_stock', '>', 0)->whereColumn('current_stock', '<=', 'minimum_stock')->count()
                : 0,
        ];

        $branchMap = $branches->keyBy('id');
        $recentOrders = (clone $focusedOrdersQuery)
            ->latest('created_at')
            ->limit(8)
            ->get(['id', 'branch_id', 'customer_name', 'payment_method', 'fulfillment_type', 'grand_total', 'created_at'])
            ->map(function ($order) use ($branchMap, $timezone): array {
                $method = strtoupper((string) ($order->payment_method ?: 'OTHER'));
                $methodLabel = match ($method) {
                    'CASH', 'TUNAI' => 'Tunai',
                    'QRIS' => 'QRIS',
                    'TRANSFER', 'BANK_TRANSFER' => 'Transfer',
                    'CARD', 'DEBIT', 'CREDIT', 'EDC' => 'Kartu / EDC',
                    default => $method === 'OTHER' ? 'Lainnya' : $method,
                };
                $channel = strtoupper((string) ($order->fulfillment_type ?: 'DINE_IN'));
                $channelLabel = match ($channel) {
                    'DINE_IN' => 'Dine-In',
                    'TAKEAWAY' => 'Takeaway',
                    'DELIVERY' => 'Delivery',
                    default => $channel,
                };
                $branch = $branchMap->get($order->branch_id);

                return [
                    'id' => $order->id,
                    'branchName' => $branch?->name ?? 'Outlet',
                    'branchSlug' => $branch?->slug,
                    'customerName' => $order->customer_name ?: 'Pelanggan Umum',
                    'paymentLabel' => $methodLabel,
                    'channelLabel' => $channelLabel,
                    'grandTotal' => (float) $order->grand_total,
                    'createdAt' => $order->created_at?->timezone($timezone),
                ];
            })
            ->values();

        return [
            'from' => $from,
            'until' => $through->subDay(),
            'focus' => $focus,
            'focusedBranches' => $focusedBranches,
            'branchStats' => $branchStats,
            'comparison' => $branches->sortByDesc(fn (Branch $branch) => $branchStats[$branch->id]['sales']),
            'sales' => $sales,
            'salesChange' => $previousSales > 0 ? (($sales - $previousSales) / $previousSales) * 100 : null,
            'orders' => $orders,
            'average' => $orders > 0 ? $sales / $orders : 0,
            'grossProfit' => $focusedStats->sum('grossProfit'),
            'trend' => $trend,
            'topProducts' => $topProducts,
            'paymentBreakdown' => $paymentBreakdown,
            'channelBreakdown' => $channelBreakdown,
            'peakHours' => $peakHours,
            'actionCenter' => $actionCenter,
            'recentOrders' => $recentOrders,
        ];
    }
}
