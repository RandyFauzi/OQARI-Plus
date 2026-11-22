<?php

namespace App\Http\Middleware;

use App\Mcp\McpOAuthResource;
use App\Models\McpConnection;
use App\Models\User;
use App\Services\BranchContext;
use App\Services\McpAccess;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Passport\AccessToken;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateOqariMcp
{
    public function __construct(private McpAccess $access, private BranchContext $branches) {}

    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(config('oqari_mcp.enabled'), 404);
        $this->branches->clear();
        $bearer = $request->bearerToken();

        $connection = match (true) {
            ! $bearer => null,
            str_starts_with($bearer, 'oqm_') => preg_match('/^oqm_[a-f0-9]{64}$/D', $bearer)
                ? McpConnection::with(['user.shop', 'shop'])->where('token_hash', hash('sha256', $bearer))->first()
                : null,
            config('oqari_mcp.oauth_enabled') => $this->oauthConnection($request, $bearer),
            default => null,
        };

        $request->attributes->set('oqari_mcp.audit_connection', $connection);
        $actor = $connection?->user;
        $role = $actor instanceof User ? $this->access->role($actor) : null;
        if (! $role || ! $connection->usableBy($actor, $role)) {
            $challenge = 'Bearer realm="OQARI MCP"';
            if (config('oqari_mcp.oauth_enabled')) {
                $metadata = route('oqari.mcp.metadata');
                $challenge .= ', resource_metadata="'.$metadata.'", scope="mcp:use"';
            }

            return response()->json(['error' => 'invalid_token'], 401, ['WWW-Authenticate' => $challenge, 'Cache-Control' => 'no-store']);
        }

        $key = 'oqari:mcp:'.$actor->id;
        if (RateLimiter::increment($key, 60) > config('oqari_mcp.requests_per_minute')) {
            return response()->json(['error' => 'rate_limit_exceeded'], 429, ['Retry-After' => (string) RateLimiter::availableIn($key)]);
        }

        if ($connection->last_used_at === null || $connection->last_used_at->lt(now()->subMinute())) {
            $connection->update(['last_used_at' => now()]);
        }
        $request->attributes->set(McpConnection::class, $connection);
        $previousGuard = Auth::getDefaultDriver();
        Auth::shouldUse('oqari-mcp-request');
        try {
            $response = $next($request);
            $response->headers->set('Cache-Control', 'private, no-store');

            return $response;
        } finally {
            Auth::shouldUse($previousGuard);
            $this->branches->clear();
        }
    }

    private function oauthConnection(Request $request, string $bearer): ?McpConnection
    {
        $guard = Auth::guard('oqari-mcp-oauth');
        $guard->forgetUser();
        $actor = $guard->user();
        $guard->forgetUser();
        if (! $actor instanceof User || ! $actor->tokenCan('mcp:use') || ! ($role = $this->access->role($actor))) {
            return null;
        }
        $token = $actor->token();
        if (! $token instanceof AccessToken || ! ($client = $token->oauth_client_id)) {
            return null;
        }

        if (! McpOAuthResource::matchesVerifiedToken($bearer, $client)) {
            $request->attributes->set('oqari_mcp.auth_error', 'oauth_invalid_audience');

            return null;
        }

        $abilities = $this->access->abilities($actor, $role);
        $connection = McpConnection::firstOrCreate(
            ['user_id' => $actor->id, 'oauth_client_id' => $client],
            [
                'shop_id' => $actor->shop_id, 'name' => $client === config('oqari_mcp.codex_client_id') ? 'Codex' : 'OAuth MCP', 'role' => $role,
                'abilities' => array_values(array_intersect($abilities, ['sales', 'platform'])),
                'branch_ids' => $actor->shop?->branches_enabled ? $this->access->branches($actor)->modelKeys() : null,
                'account_version' => $actor->account_version ?? 0,
            ],
        );

        return $connection->loadMissing(['user.shop', 'shop']);
    }
}
