<?php

namespace App\Mcp\Tools;

class GetBranchSnapshot extends ReadTool
{
    protected string $name = 'oqari_get_branch_snapshot';

    protected string $title = 'Ringkasan outlet';

    protected string $description = 'Ambil omzet, transaksi lunas, dan menu terlaris untuk satu outlet. Gunakan ini untuk menjawab penjualan dan menu terlaris hari ini sekali panggil.';

    protected const Operation = 'snapshot';

    protected const Ability = 'sales';
}
