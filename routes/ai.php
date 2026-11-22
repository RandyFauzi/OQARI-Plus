<?php

use App\Http\Middleware\AuditOqariMcp;
use App\Http\Middleware\AuthenticateOqariMcp;
use App\Mcp\McpOAuthResource;
use App\Mcp\Servers\OqariServer;
use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Facades\Mcp;
use Laravel\Mcp\Server\Middleware\AddWwwAuthenticateHeader;

Mcp::web(McpOAuthResource::Path, OqariServer::class)
    ->withoutMiddleware(AddWwwAuthenticateHeader::class)
    ->middleware([AuditOqariMcp::class, 'throttle:120,1', AuthenticateOqariMcp::class])
    ->name('oqari.mcp');

Route::get('/.well-known/oauth-protected-resource/mcp/oqari', function () {
    abort_unless(config('oqari_mcp.enabled') && config('oqari_mcp.oauth_enabled'), 404);

    return response()->json([
        'resource' => McpOAuthResource::url(),
        'authorization_servers' => [McpOAuthResource::issuer()],
        'scopes_supported' => ['mcp:use'],
    ]);
})->name('oqari.mcp.metadata');

Route::get('/.well-known/oauth-authorization-server', function () {
    abort_unless(config('oqari_mcp.enabled') && config('oqari_mcp.oauth_enabled'), 404);

    return response()->json([
        'issuer' => McpOAuthResource::issuer(),
        'authorization_response_iss_parameter_supported' => true,
        'authorization_endpoint' => route('passport.authorizations.authorize'),
        'token_endpoint' => route('passport.token'),
        'response_types_supported' => ['code'],
        'grant_types_supported' => ['authorization_code', 'refresh_token'],
        'code_challenge_methods_supported' => ['S256'],
        'token_endpoint_auth_methods_supported' => ['none', 'client_secret_basic', 'client_secret_post'],
        'scopes_supported' => ['mcp:use'],
    ]);
})->name('oqari.mcp.authorization-metadata');
