<?php

namespace App\Mcp\Tools;

class ListStockMutations extends ReadTool
{
    protected string $name = 'oqari_list_stock_mutations';

    protected string $title = 'Riwayat mutasi stok';

    protected string $description = 'Lihat riwayat pergerakan stok masuk, keluar, dan penyesuaian pada periode yang dipilih.';

    protected const Operation = 'stock_mutations';

    protected const Ability = 'inventory';
}
