<?php

namespace App\Mcp\Tools;

class ListPurchaseOrders extends ReadTool
{
    protected string $name = 'oqari_list_purchase_orders';

    protected string $title = 'Daftar purchase order';

    protected string $description = 'Lihat daftar Purchase Order (PO) ke supplier beserta status dan jumlah itemnya.';

    protected const Operation = 'purchase_orders';

    protected const Ability = 'inventory';
}
