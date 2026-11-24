<?php

namespace App\Mcp\Tools;

class ListOrders extends ReadTool
{
    protected string $name = 'oqari_list_orders';

    protected string $title = 'Daftar transaksi';

    protected string $description = 'Lihat daftar order/transaksi terbaru beserta metode bayar, status, diskon, dan nominalnya.';

    protected const Operation = 'orders';

    protected const Ability = 'sales';
}
