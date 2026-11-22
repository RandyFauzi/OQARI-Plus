<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('production_recipes')) {
            Schema::create('production_recipes', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
                $table->foreignId('output_material_id')->constrained('raw_materials')->cascadeOnDelete();
                $table->uuid('request_id');
                $table->unsignedInteger('version');
                $table->decimal('yield_quantity', 18, 6);
                $table->string('unit', 50);
                $table->string('notes', 250)->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->unique(['shop_id', 'output_material_id', 'version'], 'production_recipe_version_unique');
                $table->unique(['shop_id', 'request_id']);
            });
        }

        if (! Schema::hasTable('production_recipe_items')) {
            Schema::create('production_recipe_items', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('production_recipe_id')->constrained()->cascadeOnDelete();
                $table->foreignId('raw_material_id')->constrained()->cascadeOnDelete();
                $table->decimal('quantity', 18, 6);
                $table->string('unit', 50);
                $table->timestamps();
                $table->unique(['production_recipe_id', 'raw_material_id'], 'production_recipe_ingredient_unique');
            });
        }

        if (! Schema::hasTable('production_work_orders')) {
            Schema::create('production_work_orders', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
                $table->foreignId('production_recipe_id')->constrained()->cascadeOnDelete();
                $table->foreignId('inventory_location_id')->nullable()->constrained()->nullOnDelete();
                $table->uuid('request_id');
                $table->string('status', 20)->default('PLANNED');
                $table->decimal('target_quantity', 18, 6);
                $table->decimal('good_quantity', 18, 6)->nullable();
                $table->decimal('waste_quantity', 18, 6)->nullable();
                $table->string('waste_reason', 250)->nullable();
                $table->string('notes', 250)->nullable();
                $table->unsignedInteger('result_revision')->default(0);
                $table->decimal('total_cost', 24, 6)->nullable();
                $table->decimal('output_unit_cost', 18, 6)->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('recorded_at')->nullable();
                $table->timestamp('posted_at')->nullable();
                $table->timestamps();
                $table->unique(['shop_id', 'request_id']);
            });
        }

        if (! Schema::hasTable('production_work_order_items')) {
            Schema::create('production_work_order_items', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('production_work_order_id')->constrained()->cascadeOnDelete();
                $table->foreignId('raw_material_id')->constrained()->cascadeOnDelete();
                $table->string('unit', 50);
                $table->decimal('planned_quantity', 18, 6);
                $table->decimal('actual_quantity', 18, 6)->nullable();
                $table->decimal('unit_cost', 18, 6)->nullable();
                $table->timestamps();
                $table->unique(['production_work_order_id', 'raw_material_id'], 'production_order_ingredient_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('production_work_order_items');
        Schema::dropIfExists('production_work_orders');
        Schema::dropIfExists('production_recipe_items');
        Schema::dropIfExists('production_recipes');
    }
};
