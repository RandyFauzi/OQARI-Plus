<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saas_invoices', function (Blueprint $table): void {
            $table->id();
            $table->string('invoice_number', 64)->unique();
            $table->foreignId('shop_id')->constrained('shops')->cascadeOnDelete();
            $table->foreignId('saas_subscription_id')->nullable()->constrained('saas_subscriptions')->nullOnDelete();
            $table->foreignId('saas_plan_id')->constrained('saas_plans');
            $table->string('billing_cycle', 20)->default('monthly');
            $table->string('gateway', 30);
            $table->string('external_id', 128)->nullable()->index();
            $table->unsignedInteger('amount');
            $table->string('currency', 10)->default('IDR');
            $table->string('status', 30)->default('pending');
            $table->text('payment_url')->nullable();
            $table->string('snap_token', 255)->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamps();

            $table->index(['shop_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saas_invoices');
    }
};
