<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_domains', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('shop_id')->constrained('shops')->cascadeOnDelete();
            $table->string('domain', 253)->unique();
            $table->string('verification_token', 64);
            $table->string('status', 30)->default('pending');
            $table->string('cloudflare_hostname_id', 100)->nullable();
            $table->string('ssl_status', 30)->default('pending');
            $table->boolean('is_primary')->default(true);
            $table->text('last_error')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamps();

            $table->index(['shop_id', 'status']);
            $table->index(['domain', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_domains');
    }
};
