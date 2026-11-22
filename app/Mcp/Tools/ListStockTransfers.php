<?php

namespace App\Mcp\Tools;

class ListStockTransfers extends ReadTool
{
    protected string $name = 'oqari_list_stock_transfers';

    protected string $title = 'Riwayat transfer stok';

    protected string $description = 'Lihat jumlah bahan dikirim, diterima, dan tersisa pada transfer yang terkait lokasi yang diizinkan.';

    protected const Operation = 'transfers';

    protected const Ability = 'inventory';
}
