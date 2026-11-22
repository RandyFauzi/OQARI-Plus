<?php

namespace App\Http\Middleware;

use App\Mcp\McpOAuthResource;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Uri;
use Symfony\Component\HttpFoundation\Response;

class ValidateOqariMcpOAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(config('oqari_mcp.enabled') && config('oqari_mcp.oauth_enabled'), 404);
        abort_unless($request->routeIs(
            'passport.authorizations.authorize', 'passport.authorizations.approve',
            'passport.authorizations.deny', 'passport.token',
        ), 404);

        if ($request->routeIs('passport.authorizations.authorize', 'passport.token')) {
            $resource = McpOAuthResource::url();
            if ($request->input('resource') !== $resource) {
                return response()->json(['error' => 'invalid_target'], 400)->header('Cache-Control', 'no-store');
            }

            if ($request->routeIs('passport.authorizations.authorize') &&
                ($request->input('scope') !== 'mcp:use'
                    || $request->input('code_challenge_method') !== 'S256'
                    || ! is_string($request->input('code_challenge')))) {
                return response()->json(['error' => 'invalid_request'], 400)->header('Cache-Control', 'no-store');
            }

            if ($request->routeIs('passport.token') &&
                ! in_array($request->input('grant_type'), ['authorization_code', 'refresh_token'], true)) {
                return response()->json(['error' => 'unsupported_grant_type'], 400)->header('Cache-Control', 'no-store');
            }
        }

        $response = $next($request);
        $location = $response->headers->get('Location');
        if ($response->isRedirection() && $location && ! $request->routeIs('passport.token')) {
            $uri = Uri::of($location);
            if ($uri->query()->has('code') || $uri->query()->has('error')) {
                $response->headers->set('Location', (string) $uri->withQuery(['iss' => McpOAuthResource::issuer()]));
            }
        }

        return $response;
    }
}
