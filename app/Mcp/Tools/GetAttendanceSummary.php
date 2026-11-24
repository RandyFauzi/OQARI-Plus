<?php

namespace App\Mcp\Tools;

class GetAttendanceSummary extends ReadTool
{
    protected string $name = 'oqari_get_attendance_summary';

    protected string $title = 'Ringkasan absensi kru';

    protected string $description = 'Lihat ringkasan kehadiran, keterlambatan, dan total menit kerja kru pada periode yang dipilih.';

    protected const Operation = 'attendance';

    protected const Ability = 'operations';
}
