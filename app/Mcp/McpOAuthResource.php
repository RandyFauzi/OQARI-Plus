<?php

namespace App\Mcp;

use Illuminate\Contracts\Encryption\Encrypter;
use InvalidArgumentException;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Token\Parser;
use Lcobucci\JWT\UnencryptedToken;

class McpOAuthResource
{
    public const Path = '/mcp/oqari';

    public static function issuer(): string
    {
        return rtrim(config('app.url'), '/');
    }

    public static function url(): string
    {
        return config('oqari_mcp.public_url') ?: self::issuer().self::Path;
    }

    public static function grantKey(Encrypter $encrypter): string
    {
        return hash_hmac('sha256', 'oqari:mcp:oauth-grants:'.self::url(), $encrypter->getKey(), true);
    }

    /**
     * Inspect claims only after Passport has verified the same bearer token.
     */
    public static function matchesVerifiedToken(string $bearer, string $clientId): bool
    {
        try {
            $token = (new Parser(new JoseEncoder))->parse($bearer);
        } catch (InvalidArgumentException) {
            return false;
        }

        return $token instanceof UnencryptedToken
            && $token->claims()->get('aud') === [$clientId, self::url()];
    }
}
