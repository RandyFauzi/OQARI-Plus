<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('mcp_connections')) {
            Schema::create('mcp_connections', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->foreignId('shop_id')->nullable()->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('name', 100);
                $table->string('role', 24);
                $table->string('oauth_client_id', 191)->nullable();
                $table->char('token_hash', 64)->nullable()->unique();
                $table->char('token_suffix', 4)->nullable();
                $table->json('abilities');
                $table->json('branch_ids')->nullable();
                $table->unsignedInteger('account_version');
                $table->timestamp('expires_at')->nullable();
                $table->timestamp('revoked_at')->nullable();
                $table->timestamp('last_used_at')->nullable();
                $table->timestamps();
                $table->unique(['user_id', 'oauth_client_id']);
            });
        }

        if (! Schema::hasTable('mcp_tool_calls')) {
            Schema::create('mcp_tool_calls', function (Blueprint $table): void {
                $table->id();
                $table->foreignUuid('mcp_connection_id')->constrained()->cascadeOnDelete();
                $table->uuid('request_id');
                $table->string('tool', 100);
                $table->string('outcome', 24);
                $table->timestamp('created_at')->index();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('mcp_tool_calls');
        Schema::dropIfExists('mcp_connections');
    }
};
