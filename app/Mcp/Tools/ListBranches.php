<?php

namespace App\Mcp\Tools;

class ListBranches extends ReadTool
{
    protected string $name = 'oqari_list_branches';

    protected string $title = 'Daftar cabang';

    protected string $description = 'Temukan ID cabang yang bisa diakses. Gunakan ID saat nama cabang ambigu.';

    protected const Operation = 'branches';

    protected const Ability = 'tenant';
}
