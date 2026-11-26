<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table): void {
            $table->string('static_qris_path')->nullable();
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->string('payment_channel', 32)->nullable();
            $table->string('payment_qr_path')->nullable();
            $table->unique(['branch_id', 'payment_channel', 'payment_reference'], 'orders_branch_channel_reference_unique');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropUnique('orders_branch_channel_reference_unique');
            $table->dropColumn(['payment_channel', 'payment_qr_path']);
        });

        Schema::table('branches', function (Blueprint $table): void {
            $table->dropColumn('static_qris_path');
        });
    }
};
