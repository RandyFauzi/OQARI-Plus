<?php

namespace App\Http\Middleware;

use App\Mcp\Servers\OqariServer;
use App\Models\McpConnection;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class AuditOqariMcp
{
    public function handle(Request $request, Closure $next): Response
    {
        $startedAt = hrtime(true);
        $traceId = (string) Str::uuid();
        $request->attributes->set('oqari_mcp.trace_id', $traceId);

        $response = $next($request);
        $status = $response->getStatusCode();
        $connection = $request->attributes->get('oqari_mcp.audit_connection');
        $call = $request->attributes->get('oqari_mcp.tool_call');

        try {
            if ($connection instanceof McpConnection && ($request->json('method') === 'tools/call' || in_array($status, [401, 403, 429], true))) {
                $toolName = $call['tool'] ?? ($request->json('method') === 'tools/call'
                    ? OqariServer::knownToolName($request->json('params.name')) ?? 'tools/call'
                    : 'protocol');
                $this->record($connection, $traceId, $status, $startedAt, $toolName, $call);
            } elseif (in_array($status, [401, 403], true)) {
                Log::notice('OQARI MCP authentication refused', [
                    'trace_id' => $traceId,
                    'http_status' => $status,
                    'error_code' => $request->attributes->get('oqari_mcp.auth_error', 'access_denied'),
                    'duration_ms' => $this->duration($startedAt),
                ]);
            }
        } catch (Throwable $exception) {
            Log::error('OQARI MCP audit write failed', ['trace_id' => $traceId]);
            report($exception);
            $response = response()->json(['error' => 'audit_unavailable'], 503);
        }

        $response->headers->set('X-OQARI-Trace-Id', $traceId);
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }

    /** @param array{tool: string, outcome: string, error_code: ?string}|null $call */
    private function record(McpConnection $connection, string $traceId, int $status, int $startedAt, string $toolName, ?array $call): void
    {
        $errorCode = match ($status) {
            401, 403 => 'access_denied',
            429 => 'rate_limited',
            default => 'tool_unavailable',
        };
        DB::table('mcp_tool_calls')->insert([
            'mcp_connection_id' => $connection->id,
            'request_id' => $traceId,
            'tool' => $toolName,
            'outcome' => $call['outcome'] ?? 'denied',
            'error_code' => $call ? $call['error_code'] : $errorCode,
            'duration_ms' => $this->duration($startedAt),
            'http_status' => $status,
            'created_at' => now(),
        ]);
    }

    private function duration(int $startedAt): int
    {
        return (int) round((hrtime(true) - $startedAt) / 1_000_000);
    }
}
