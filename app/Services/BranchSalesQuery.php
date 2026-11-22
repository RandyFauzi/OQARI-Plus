<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BranchSalesQuery
{
    /** @param  list<int>|null  $branchIds  Null represents a business with legacy single-outlet records. */
    public function paidOrders(int $shopId, ?array $branchIds): Builder
    {
        return Order::withoutGlobalScope('branch')->where('shop_id', $shopId)
            ->when($branchIds !== null, fn (Builder $query) => $query->whereIn('branch_id', $branchIds))
            ->where('payment_status', 'PAID')
            ->whereNotIn('order_status', ['CANCELLED', 'Dibatalkan', 'Cancelled']);
    }

    public function topProducts(Builder $orders, int $limit): Collection
    {
        $products = DB::table('order_items')
            ->joinSub((clone $orders)->select('id', 'branch_id')->toBase(), 'paid_orders', 'paid_orders.id', '=', 'order_items.order_id')
            ->where(fn ($query) => $query->whereNull('order_items.status')->orWhere('order_items.status', '!=', 'cancelled'))
            ->select('paid_orders.branch_id', 'order_items.product_name')
            ->selectRaw('SUM(order_items.quantity) AS units')
            ->groupBy('paid_orders.branch_id', 'order_items.product_name');
        $ranked = DB::query()->fromSub($products, 'branch_products')->select('*')
            ->selectRaw('ROW_NUMBER() OVER (PARTITION BY branch_id ORDER BY units DESC, product_name ASC) AS ranking');

        return DB::query()->fromSub($ranked, 'ranked_products')
            ->where('ranking', '<=', $limit)->orderBy('ranking')->orderBy('branch_id')->get();
    }
}
