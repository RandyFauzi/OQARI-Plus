<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('branch_products')) {
            Schema::create('branch_products', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
                $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
                $table->boolean('is_available')->default(true);
                $table->boolean('is_sold_out')->nullable();
                $table->decimal('price_override', 12, 2)->nullable();
                $table->decimal('discount_price_override', 12, 2)->nullable();
                $table->boolean('is_discount_active_override')->nullable();
                $table->timestamps();

                $table->unique(['branch_id', 'product_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('branch_products');
    }
};
