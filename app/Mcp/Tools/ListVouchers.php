<?php

namespace App\Mcp\Tools;

class ListVouchers extends ReadTool
{
    protected string $name = 'oqari_list_vouchers';

    protected string $title = 'Daftar voucher dan promo';

    protected string $description = 'Lihat daftar voucher diskon yang terdaftar, kuota pemakaian, dan masa berlakunya.';

    protected const Operation = 'vouchers';

    protected const Ability = 'sales';
}
