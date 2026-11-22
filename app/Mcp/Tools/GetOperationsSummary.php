<?php

namespace App\Mcp\Tools;

class GetOperationsSummary extends ReadTool
{
    protected string $name = 'oqari_get_operations_summary';

    protected string $title = 'Ringkasan operasional';

    protected string $description = 'Lihat jumlah permintaan barang, opname, dan pembelian kecil menurut status dan periode.';

    protected const Operation = 'operations';

    protected const Ability = 'operations';
}
