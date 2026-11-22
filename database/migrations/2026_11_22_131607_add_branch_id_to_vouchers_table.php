<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('vouchers', 'branch_id')) {
            Schema::table('vouchers', function (Blueprint $table): void {
                $table->foreignId('branch_id')
                    ->nullable()
                    ->after('shop_id')
                    ->constrained('branches')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('vouchers', 'branch_id')) {
            Schema::table('vouchers', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('branch_id');
            });
        }
    }
};
