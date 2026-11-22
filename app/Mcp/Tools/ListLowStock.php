<?php

namespace App\Mcp\Tools;

class ListLowStock extends ReadTool
{
    protected string $name = 'oqari_list_low_stock';

    protected string $title = 'Stok di bawah minimum';

    protected string $description = 'Lihat saldo bahan di bawah stok minimum pada lokasi yang diizinkan.';

    protected const Operation = 'low_stock';

    protected const Ability = 'inventory';
}
