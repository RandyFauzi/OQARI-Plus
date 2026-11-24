<?php

namespace App\Mcp\Tools;

class GetSalesBreakdown extends ReadTool
{
    protected string $name = 'oqari_get_sales_breakdown';

    protected string $title = 'Rincian penjualan';

    protected string $description = 'Lihat rincian omzet per metode bayar, tipe layanan, tren harian, dan jam ramai.';

    protected const Operation = 'sales_breakdown';

    protected const Ability = 'sales';
}
