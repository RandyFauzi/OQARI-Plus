<?php

namespace App\Mcp;

use DateTimeImmutable;
use Laravel\Passport\Bridge\AccessToken;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use League\OAuth2\Server\AuthorizationValidators\BearerTokenValidator;
use League\OAuth2\Server\CryptKeyInterface;

class McpOAuthAccessToken extends AccessToken
{
    private CryptKeyInterface $signingKey;

    public function setPrivateKey(CryptKeyInterface $privateKey): void
    {
        $this->signingKey = $privateKey;
    }

    public function toString(): string
    {
        $signer = new Sha256;
        $key = InMemory::plainText($this->signingKey->getKeyContents(), $this->signingKey->getPassPhrase() ?? '');
        $jwt = Configuration::forAsymmetricSigner($signer, $key, $key);
        $clientId = $this->getClient()->getIdentifier();
        $issuedAt = new DateTimeImmutable;

        /** @see BearerTokenValidator */
        return $jwt->builder()
            ->permittedFor($clientId, McpOAuthResource::url())
            ->identifiedBy($this->getIdentifier())
            ->issuedAt($issuedAt)
            ->canOnlyBeUsedAfter($issuedAt)
            ->expiresAt($this->getExpiryDateTime())
            ->relatedTo($this->getUserIdentifier() ?? $clientId)
            ->withClaim('scopes', $this->getScopes())
            ->getToken($signer, $key)
            ->toString();
    }
}
