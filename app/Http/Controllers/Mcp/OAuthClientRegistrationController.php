<?php

namespace App\Http\Controllers\Mcp;

use App\Http\Controllers\Controller;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Passport\ClientRepository;

class OAuthClientRegistrationController extends Controller
{
    public function __invoke(Request $request, ClientRepository $clients): JsonResponse
    {
        abort_unless(config('oqari_mcp.enabled') && config('oqari_mcp.oauth_enabled'), 404);

        try {
            $data = Validator::make($request->all(), [
                'client_name' => ['sometimes', 'string', 'max:100'],
                'redirect_uris' => ['required', 'array', 'min:1', 'max:10'],
                'redirect_uris.*' => ['required', 'string', 'max:500', function (string $attribute, mixed $value, Closure $fail): void {
                    if (! is_string($value) || ! $this->isAllowedRedirectUri($value)) {
                        $fail('The redirect URI must use HTTPS or localhost.');
                    }
                }],
                'token_endpoint_auth_method' => ['sometimes', 'string', Rule::in(['none', 'client_secret_basic', 'client_secret_post'])],
                'grant_types' => ['sometimes', 'array'],
                'grant_types.*' => ['string', Rule::in(['authorization_code', 'refresh_token'])],
                'response_types' => ['sometimes', 'array'],
                'response_types.*' => ['string', Rule::in(['code'])],
                'scope' => ['sometimes', 'string', 'max:200'],
            ])->validate();
        } catch (ValidationException $exception) {
            return response()->json([
                'error' => 'invalid_client_metadata',
                'error_description' => $exception->validator->errors()->first(),
            ], 400);
        }

        $name = trim((string) ($data['client_name'] ?? 'MCP OAuth Client')) ?: 'MCP OAuth Client';
        $redirectUris = array_values(array_unique($data['redirect_uris']));
        $authMethod = $data['token_endpoint_auth_method'] ?? 'none';
        $confidential = $authMethod !== 'none';

        $client = $clients->createAuthorizationCodeGrantClient($name, $redirectUris, confidential: $confidential);

        $payload = [
            'client_id' => $client->id,
            'client_name' => $client->name,
            'redirect_uris' => $redirectUris,
            'grant_types' => $data['grant_types'] ?? ['authorization_code', 'refresh_token'],
            'response_types' => $data['response_types'] ?? ['code'],
            'token_endpoint_auth_method' => $authMethod,
            'scope' => 'mcp:use',
            'client_id_issued_at' => time(),
        ];

        if ($confidential && filled($client->plainSecret)) {
            $payload['client_secret'] = $client->plainSecret;
            $payload['client_secret_expires_at'] = 0;
        }

        return response()->json($payload, 201, ['Cache-Control' => 'no-store']);
    }

    private function isAllowedRedirectUri(string $uri): bool
    {
        $parts = parse_url($uri);
        if (! is_array($parts) || isset($parts['fragment'])) {
            return false;
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));
        if ($host === '') {
            return false;
        }

        if ($scheme === 'https') {
            return true;
        }

        return $scheme === 'http' && in_array($host, ['localhost', '127.0.0.1'], true);
    }
}
