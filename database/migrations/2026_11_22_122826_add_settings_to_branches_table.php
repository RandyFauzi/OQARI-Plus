<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('branches') && ! Schema::hasColumn('branches', 'settings')) {
            Schema::table('branches', function (Blueprint $table): void {
                $table->json('settings')->nullable()->after('is_active');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('branches') && Schema::hasColumn('branches', 'settings')) {
            Schema::table('branches', function (Blueprint $table): void {
                $table->dropColumn('settings');
            });
        }
    }
};
