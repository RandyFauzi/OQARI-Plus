<?php

namespace App\Mcp\Tools;

class GetPlatformSummary extends ReadTool
{
    protected string $name = 'oqari_get_platform_summary';

    protected string $title = 'Ringkasan platform';

    protected string $description = 'Lihat jumlah bisnis OQARI menurut status akun.';

    protected const Operation = 'platform';

    protected const Ability = 'platform';
}
