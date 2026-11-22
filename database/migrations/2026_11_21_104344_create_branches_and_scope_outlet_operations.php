<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const OutletTables = [
        'orders', 'tables', 'cash_registers', 'attendances', 'crew_shifts', 'shift_templates',
        'printers', 'inventory_locations', 'outlet_stock_requests', 'stock_counts',
        'petty_purchases', 'activity_logs', 'stock_mutations',
    ];

    public function up(): void
    {
        if (! Schema::hasColumn('shops', 'branches_enabled')) {
            Schema::table('shops', function (Blueprint $table): void {
                $table->boolean('branches_enabled')->default(false);
            });
        }
        if (! Schema::hasTable('branches')) {
            Schema::create('branches', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
                $table->string('slug', 64);
                $table->string('name', 120);
                $table->string('address')->nullable();
                $table->boolean('is_primary')->default(false);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->unique(['shop_id', 'slug']);
            });
        }
        if (! Schema::hasTable('branch_memberships')) {
            Schema::create('branch_memberships', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('role', 24);
                $table->timestamps();
                $table->unique(['branch_id', 'user_id']);
                $table->index(['user_id', 'branch_id']);
            });
        }
        foreach (self::OutletTables as $name) {
            if (! Schema::hasColumn($name, 'branch_id')) {
                Schema::table($name, function (Blueprint $table): void {
                    $table->foreignId('branch_id')->nullable()->constrained()->restrictOnDelete();
                });
            }
        }
        if (! Schema::hasIndex('orders', ['branch_id', 'created_at'])) {
            Schema::table('orders', function (Blueprint $table): void {
                $table->index(['branch_id', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropIndex(['branch_id', 'created_at']);
        });
        foreach (self::OutletTables as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->dropConstrainedForeignId('branch_id');
            });
        }
        Schema::dropIfExists('branch_memberships');
        Schema::dropIfExists('branches');
        Schema::table('shops', function (Blueprint $table): void {
            $table->dropColumn('branches_enabled');
        });
    }
};
