<?php

namespace App\Mcp\Tools;

class ListProductionOrders extends ReadTool
{
    protected string $name = 'oqari_list_production_orders';

    protected string $title = 'Ringkasan produksi';

    protected string $description = 'Lihat daftar perintah kerja produksi bahan setengah jadi/jadi beserta hasil dan waste-nya.';

    protected const Operation = 'production_orders';

    protected const Ability = 'operations';
}
