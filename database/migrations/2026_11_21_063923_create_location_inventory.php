<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('inventory_locations')) {
            Schema::create('inventory_locations', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
                $table->string('code', 20);
                $table->string('name');
                $table->timestamps();
                $table->unique(['shop_id', 'code']);
            });
        }

        if (! Schema::hasTable('inventory_balances')) {
            Schema::create('inventory_balances', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('inventory_location_id')->constrained()->cascadeOnDelete();
                $table->foreignId('raw_material_id')->constrained()->cascadeOnDelete();
                $table->decimal('quantity', 18, 6);
                $table->timestamps();
                $table->unique(['inventory_location_id', 'raw_material_id'], 'inventory_balance_location_material_unique');
            });
        }

        if (! Schema::hasTable('stock_transfers')) {
            Schema::create('stock_transfers', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
                $table->uuid('request_id');
                $table->foreignId('from_location_id')->constrained('inventory_locations')->cascadeOnDelete();
                $table->foreignId('to_location_id')->constrained('inventory_locations')->cascadeOnDelete();
                $table->foreignId('raw_material_id')->constrained()->cascadeOnDelete();
                $table->decimal('sent_quantity', 18, 6);
                $table->decimal('received_quantity', 18, 6)->default(0);
                $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('received_at')->nullable();
                $table->timestamps();
                $table->unique(['shop_id', 'request_id']);
            });
        }

        if (! Schema::hasTable('modifier_recipes')) {
            Schema::create('modifier_recipes', function (Blueprint $table): void {
                $table->foreignId('modifier_id')->constrained()->cascadeOnDelete();
                $table->foreignId('raw_material_id')->constrained()->cascadeOnDelete();
                $table->decimal('amount', 18, 6);
                $table->primary(['modifier_id', 'raw_material_id']);
            });
        }

        if (! Schema::hasColumn('stock_mutations', 'inventory_location_id')) {
            Schema::table('stock_mutations', function (Blueprint $table): void {
                $table->foreignId('inventory_location_id')->nullable()->constrained()->nullOnDelete();
                $table->decimal('unit_cost', 18, 6)->nullable();
                $table->index(['shop_id', 'reference_type', 'reference_id'], 'stock_mutation_reference_index');
            });
        }

        if (! Schema::hasColumn('orders', 'qr_request_id')) {
            Schema::table('orders', function (Blueprint $table): void {
                $table->uuid('qr_request_id')->nullable();
                $table->unique(['shop_id', 'qr_request_id']);
            });
        }

        $backfillPostedItems = ! Schema::hasColumn('order_items', 'stock_posted_at');
        if ($backfillPostedItems) {
            Schema::table('order_items', fn (Blueprint $table) => $table->timestamp('stock_posted_at')->nullable());
        }

        if (! Schema::hasColumn('order_items', 'batch_request_id')) {
            Schema::table('order_items', function (Blueprint $table): void {
                $table->uuid('batch_request_id')->nullable();
                $table->index(['order_id', 'batch_request_id']);
            });
        }

        DB::transaction(function () use ($backfillPostedItems): void {
            DB::table('shops')->select('id', 'slug')->chunkById(200, function ($shops): void {
                foreach ($shops as $shop) {
                    $now = now();
                    DB::table('inventory_locations')->insertOrIgnore([
                        'shop_id' => $shop->id,
                        'code' => 'outlet',
                        'name' => $shop->slug === 'burgerkat-demo' ? 'Outlet Demo' : 'Outlet Utama',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                    $outletId = DB::table('inventory_locations')->where('shop_id', $shop->id)->where('code', 'outlet')->value('id');
                    DB::table('raw_materials')->where('shop_id', $shop->id)->select('id', 'current_stock')
                        ->chunkById(500, function ($materials) use ($outletId, $now): void {
                            DB::table('inventory_balances')->insertOrIgnore($materials->map(fn ($material) => [
                                'inventory_location_id' => $outletId, 'raw_material_id' => $material->id,
                                'quantity' => $material->current_stock, 'created_at' => $now, 'updated_at' => $now,
                            ])->all());
                        });
                    DB::table('stock_mutations')->where('shop_id', $shop->id)->whereNull('inventory_location_id')->update(['inventory_location_id' => $outletId]);
                }
            });
            if (! $backfillPostedItems) {
                return;
            }
            DB::table('orders')->select('id')->whereExists(function ($query): void {
                $query->selectRaw('1')->from('stock_mutations')
                    ->whereColumn('stock_mutations.shop_id', 'orders.shop_id')
                    ->whereColumn('reference_id', 'orders.id')
                    ->where('reference_type', 'Order')->where('type', 'out_order');
            })->chunkById(500, function ($orders): void {
                DB::table('order_items')->whereIn('order_id', $orders->pluck('id'))->update(['stock_posted_at' => now()]);
            });
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table): void {
            $table->dropIndex(['order_id', 'batch_request_id']);
            $table->dropColumn(['stock_posted_at', 'batch_request_id']);
        });
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropUnique(['shop_id', 'qr_request_id']);
            $table->dropColumn('qr_request_id');
        });
        Schema::table('stock_mutations', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('inventory_location_id');
            $table->dropIndex('stock_mutation_reference_index');
            $table->dropColumn('unit_cost');
        });
        Schema::dropIfExists('modifier_recipes');
        Schema::dropIfExists('stock_transfers');
        Schema::dropIfExists('inventory_balances');
        Schema::dropIfExists('inventory_locations');
    }
};
