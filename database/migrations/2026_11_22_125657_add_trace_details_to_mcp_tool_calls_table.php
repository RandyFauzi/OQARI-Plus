<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('mcp_tool_calls', 'duration_ms')) {
            return;
        }

        Schema::table('mcp_tool_calls', function (Blueprint $table): void {
            $table->unsignedInteger('duration_ms')->nullable();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->string('error_code', 40)->nullable();
            $table->index('request_id', 'mcp_tool_calls_trace_index');
        });
    }

    public function down(): void
    {
        Schema::table('mcp_tool_calls', function (Blueprint $table): void {
            $table->dropIndex('mcp_tool_calls_trace_index');
            $table->dropColumn(['duration_ms', 'http_status', 'error_code']);
        });
    }
};
