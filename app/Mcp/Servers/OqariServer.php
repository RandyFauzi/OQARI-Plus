<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\CompareBranchSales;
use App\Mcp\Tools\GetAttendanceSummary;
use App\Mcp\Tools\GetBranchSnapshot;
use App\Mcp\Tools\GetContext;
use App\Mcp\Tools\GetOperationsSummary;
use App\Mcp\Tools\GetPlatformSummary;
use App\Mcp\Tools\GetSalesBreakdown;
use App\Mcp\Tools\GetSalesSummary;
use App\Mcp\Tools\GetTopProducts;
use App\Mcp\Tools\ListBranches;
use App\Mcp\Tools\ListCashRegisterSessions;
use App\Mcp\Tools\ListLowStock;
use App\Mcp\Tools\ListOrders;
use App\Mcp\Tools\ListProductionOrders;
use App\Mcp\Tools\ListProducts;
use App\Mcp\Tools\ListPurchaseOrders;
use App\Mcp\Tools\ListRawMaterials;
use App\Mcp\Tools\ListStockMutations;
use App\Mcp\Tools\ListStockTransfers;
use App\Mcp\Tools\ListVouchers;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Tool;

class OqariServer extends Server
{
    protected string $name = 'OQARI Advance';

    protected string $version = '1.0.0';

    public int $defaultPaginationLength = 50;

    protected string $instructions = 'Gunakan cakupan koneksi aktif. Untuk pertanyaan penjualan dan menu terlaris pada satu outlet gunakan oqari_get_branch_snapshot. Jika cabang ambigu, gunakan oqari_list_branches. Sajikan tanggal, zona waktu, dan nominal IDR sesuai hasil. Semua tool hanya membaca data.';

    private const Tools = [
        GetContext::class,
        ListBranches::class,
        GetBranchSnapshot::class,
        GetSalesSummary::class,
        CompareBranchSales::class,
        GetTopProducts::class,
        ListProducts::class,
        GetSalesBreakdown::class,
        ListOrders::class,
        ListVouchers::class,
        ListLowStock::class,
        ListRawMaterials::class,
        ListStockTransfers::class,
        ListStockMutations::class,
        ListPurchaseOrders::class,
        GetOperationsSummary::class,
        ListCashRegisterSessions::class,
        GetAttendanceSummary::class,
        ListProductionOrders::class,
        GetPlatformSummary::class,
    ];

    /** @var array<int, class-string<Tool>> */
    protected array $tools = self::Tools;

    public static function knownToolName(mixed $name): ?string
    {
        if (! is_string($name)) {
            return null;
        }

        foreach (self::Tools as $tool) {
            if (app($tool)->name() === $name) {
                return $name;
            }
        }

        return null;
    }
}
