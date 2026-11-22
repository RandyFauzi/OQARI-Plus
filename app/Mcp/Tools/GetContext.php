<?php

namespace App\Mcp\Tools;

class GetContext extends ReadTool
{
    protected string $name = 'oqari_get_context';

    protected string $title = 'Konteks koneksi';

    protected string $description = 'Lihat peran, zona waktu, dan cakupan data koneksi ini.';

    protected const Operation = 'context';

    protected const Ability = null;
}
