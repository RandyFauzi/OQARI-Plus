<?php

namespace App\Mcp\Tools;

class CompareBranchSales extends ReadTool
{
    protected string $name = 'oqari_compare_branch_sales';

    protected string $title = 'Perbandingan penjualan cabang';

    protected string $description = 'Bandingkan omzet dan transaksi lunas dari cabang yang diizinkan.';

    protected const Operation = 'compare';

    protected const Ability = 'sales';
}
