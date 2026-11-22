<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('suppliers')) {
            Schema::create('suppliers', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
                $table->string('name', 120);
                $table->string('contact', 160)->nullable();
                $table->timestamps();
                $table->unique(['shop_id', 'name']);
            });
        }

        if (! Schema::hasTable('purchase_orders')) {
            Schema::create('purchase_orders', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
                $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
                $table->uuid('request_id');
                $table->string('request_hash', 64);
                $table->string('status', 20)->default('DRAFT');
                $table->string('notes', 250)->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('approved_at')->nullable();
                $table->timestamps();
                $table->unique(['shop_id', 'request_id']);
            });
        }

        if (! Schema::hasTable('purchase_order_items')) {
            Schema::create('purchase_order_items', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
                $table->foreignId('raw_material_id')->constrained()->cascadeOnDelete();
                $table->string('unit', 50);
                $table->decimal('quantity', 18, 6);
                $table->decimal('unit_cost', 18, 6);
                $table->decimal('accepted_quantity', 18, 6)->default(0);
                $table->timestamps();
                $table->unique(['purchase_order_id', 'raw_material_id']);
            });
        }

        if (! Schema::hasTable('goods_receipts')) {
            Schema::create('goods_receipts', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
                $table->foreignId('inventory_location_id')->nullable()->constrained()->nullOnDelete();
                $table->uuid('request_id');
                $table->string('request_hash', 64);
                $table->string('delivery_reference', 120);
                $table->string('notes', 250)->nullable();
                $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->unique(['purchase_order_id', 'request_id']);
                $table->unique(['purchase_order_id', 'delivery_reference']);
            });
        }

        if (! Schema::hasTable('goods_receipt_items')) {
            Schema::create('goods_receipt_items', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('goods_receipt_id')->constrained()->cascadeOnDelete();
                $table->foreignId('purchase_order_item_id')->constrained()->cascadeOnDelete();
                $table->decimal('accepted_quantity', 18, 6);
                $table->decimal('rejected_quantity', 18, 6);
                $table->string('rejection_reason', 250)->nullable();
                $table->timestamps();
                $table->unique(['goods_receipt_id', 'purchase_order_item_id'], 'receipt_purchase_item_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('goods_receipt_items');
        Schema::dropIfExists('goods_receipts');
        Schema::dropIfExists('purchase_order_items');
        Schema::dropIfExists('purchase_orders');
        Schema::dropIfExists('suppliers');
    }
};
