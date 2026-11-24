<?php

namespace App\Mcp\Tools;

class ListRawMaterials extends ReadTool
{
    protected string $name = 'oqari_list_raw_materials';

    protected string $title = 'Daftar bahan baku';

    protected string $description = 'Lihat daftar lengkap bahan baku, stok saat ini di tiap lokasi, dan harga satuannya.';

    protected const Operation = 'raw_materials';

    protected const Ability = 'inventory';
}
