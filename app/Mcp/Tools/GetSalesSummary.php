<?php

namespace App\Mcp\Tools;

class GetSalesSummary extends ReadTool
{
    protected string $name = 'oqari_get_sales_summary';

    protected string $title = 'Ringkasan penjualan';

    protected string $description = 'Lihat omzet bersih dan transaksi lunas pada periode atau cabang yang dipilih.';

    protected const Operation = 'summary';

    protected const Ability = 'sales';
}
