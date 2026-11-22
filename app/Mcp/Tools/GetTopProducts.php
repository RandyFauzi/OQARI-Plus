<?php

namespace App\Mcp\Tools;

class GetTopProducts extends ReadTool
{
    protected string $name = 'oqari_get_top_products';

    protected string $title = 'Menu terlaris';

    protected string $description = 'Lihat peringkat menu berdasarkan jumlah terjual dalam transaksi lunas, dirinci per cabang.';

    protected const Operation = 'products';

    protected const Ability = 'sales';
}
