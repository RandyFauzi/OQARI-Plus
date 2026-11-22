<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('outlet_stock_requests')) {
            Schema::create('outlet_stock_requests', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
                $table->foreignId('raw_material_id')->constrained()->cascadeOnDelete();
                $table->foreignId('stock_transfer_id')->nullable()->constrained()->nullOnDelete();
                $table->uuid('request_id');
                $table->string('source', 20);
                $table->string('unit', 50);
                $table->decimal('requested_quantity', 18, 6);
                $table->decimal('approved_quantity', 18, 6)->nullable();
                $table->string('status', 20)->default('PENDING');
                $table->string('notes', 250)->nullable();
                $table->string('decision_note', 250)->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('reviewed_at')->nullable();
                $table->timestamps();
                $table->unique(['shop_id', 'request_id']);
            });
        }

        if (! Schema::hasTable('stock_counts')) {
            Schema::create('stock_counts', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
                $table->foreignId('inventory_location_id')->constrained()->cascadeOnDelete();
                $table->uuid('request_id');
                $table->string('frequency', 10);
                $table->date('counted_on');
                $table->date('period_start');
                $table->date('period_end');
                $table->string('status', 20)->default('PENDING');
                $table->string('notes', 250)->nullable();
                $table->string('decision_note', 250)->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('reviewed_at')->nullable();
                $table->timestamps();
                $table->unique(['shop_id', 'request_id']);
                $table->index(['shop_id', 'period_start']);
            });
        }

        if (! Schema::hasTable('stock_count_items')) {
            Schema::create('stock_count_items', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('stock_count_id')->constrained()->cascadeOnDelete();
                $table->foreignId('raw_material_id')->constrained()->cascadeOnDelete();
                $table->unsignedBigInteger('last_mutation_id')->nullable();
                $table->string('unit', 50);
                $table->decimal('system_quantity', 18, 6);
                $table->decimal('counted_quantity', 18, 6);
                $table->decimal('difference', 18, 6);
                $table->timestamps();
                $table->unique(['stock_count_id', 'raw_material_id']);
            });
        }

        if (! Schema::hasTable('petty_purchases')) {
            Schema::create('petty_purchases', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
                $table->uuid('request_id');
                $table->string('description', 150);
                $table->string('notes', 250)->nullable();
                $table->string('proof_path');
                $table->string('proof_name');
                $table->decimal('amount', 12, 2);
                $table->string('funding', 20);
                $table->string('status', 20)->default('PENDING');
                $table->foreignId('cash_register_session_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('cash_drawer_transaction_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('raw_material_id')->nullable()->constrained()->nullOnDelete();
                $table->decimal('quantity', 18, 6)->nullable();
                $table->string('unit', 50)->nullable();
                $table->string('decision_note', 250)->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('reviewed_at')->nullable();
                $table->timestamp('received_at')->nullable();
                $table->timestamps();
                $table->unique(['shop_id', 'request_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('petty_purchases');
        Schema::dropIfExists('stock_count_items');
        Schema::dropIfExists('stock_counts');
        Schema::dropIfExists('outlet_stock_requests');
    }
};
