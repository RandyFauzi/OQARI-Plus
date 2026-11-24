<?php

namespace App\Mcp\Tools;

class ListProducts extends ReadTool
{
    protected string $name = 'oqari_list_products';

    protected string $title = 'Daftar menu terdaftar';

    protected string $description = 'Lihat total dan daftar menu terdaftar beserta kategori, harga, status stok, dan ketersediaan cabang.';

    protected const Operation = 'catalog_products';

    protected const Ability = 'sales';
}
