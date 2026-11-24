<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('mcp_connections', 'token_encrypted')) {
            Schema::table('mcp_connections', function (Blueprint $table): void {
                $table->text('token_encrypted')->nullable()->after('token_suffix');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('mcp_connections', 'token_encrypted')) {
            Schema::table('mcp_connections', function (Blueprint $table): void {
                $table->dropColumn('token_encrypted');
            });
        }
    }
};
