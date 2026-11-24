<?php

namespace App\Mcp\Tools;

class ListCashRegisterSessions extends ReadTool
{
    protected string $name = 'oqari_list_cash_register_sessions';

    protected string $title = 'Ringkasan shift kasir';

    protected string $description = 'Lihat riwayat buka/tutup laci kasir, modal awal, penjualan per metode, dan selisih kas.';

    protected const Operation = 'cash_sessions';

    protected const Ability = 'operations';
}
