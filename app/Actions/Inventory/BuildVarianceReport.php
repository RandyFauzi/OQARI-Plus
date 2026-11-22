<?php

namespace App\Actions\Inventory;

use App\Models\CashRegisterSession;
use App\Models\ProductionWorkOrder;
use App\Models\StockCountItem;
use App\Models\StockTransfer;
use App\Services\BranchContext;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class BuildVarianceReport
{
    public const Types = ['counts' => 'Opname', 'transfers' => 'Transfer', 'production' => 'Produksi', 'cash' => 'Kas shift'];

    public function execute(int $shopId, string $type, ?string $from, ?string $through): LengthAwarePaginator
    {
        if ($type === 'counts') {
            return StockCountItem::where('difference', '!=', 0)
                ->whereHas('stockCount', fn ($query) => $query->where('shop_id', $shopId)
                    ->when($from, fn ($scope) => $scope->where('period_end', '>=', $from))
                    ->when($through, fn ($scope) => $scope->where('period_start', '<=', $through)))
                ->with(['stockCount.location', 'rawMaterial'])->latest('id')->paginate(25)->withQueryString();
        }

        [$query, $dateColumn] = match ($type) {
            'transfers' => [StockTransfer::where('shop_id', $shopId)->whereColumn('received_quantity', '<', 'sent_quantity')
                ->when(app(BranchContext::class)->id($shopId), fn ($query, int $branchId) => $query->whereHas('toLocation', fn ($location) => $location->where('branch_id', $branchId)))
                ->with(['rawMaterial', 'fromLocation', 'toLocation']), 'created_at'],
            'production' => [ProductionWorkOrder::where('shop_id', $shopId)->where('status', ProductionWorkOrder::Posted)
                ->where(fn ($scope) => $scope->whereColumn('good_quantity', '!=', 'target_quantity')->orWhere('waste_quantity', '>', 0))
                ->with('recipe.outputMaterial'), 'posted_at'],
            'cash' => [CashRegisterSession::whereHas('register', fn ($scope) => $scope->where('shop_id', $shopId))
                ->where('status', 'CLOSED')->where('difference', '!=', 0)->with(['register', 'user']), 'closed_at'],
        };

        $timezone = config('attendance.timezone');

        return $query
            ->when($from, fn ($scope) => $scope->where($dateColumn, '>=', CarbonImmutable::parse($from, $timezone)->startOfDay()->utc()))
            ->when($through, fn ($scope) => $scope->where($dateColumn, '<', CarbonImmutable::parse($through, $timezone)->addDay()->startOfDay()->utc()))
            ->latest($dateColumn)->latest('id')->paginate(25)->withQueryString();
    }
}
