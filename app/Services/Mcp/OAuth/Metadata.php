<?php

namespace App\Services\Mcp\OAuth;

use App\Services\Mcp\DelegatedContext;

final class Metadata
{
    public function issuer(): string
    {
        $issuer = config('mcp.issuer');
        $resource = config('mcp.resource');
        $parts = is_string($issuer) ? parse_url($issuer) : false;
        OAuthError::require(config('mcp.enabled') === true && is_array($parts) && ($parts['scheme'] ?? null) === 'https' && isset($parts['host']) && filter_var($issuer, FILTER_VALIDATE_URL) && ! isset($parts['user']) && ! isset($parts['pass']) && ! isset($parts['query']) && ! isset($parts['fragment']) && ! isset($parts['path']) && $resource === $issuer.'/mcp', 'not_found', 404);

        return $issuer;
    }

    public function resource(): string
    {
        return $this->issuer().'/mcp';
    }

    public function protectedResource(): array
    {
        return ['resource' => $this->resource(), 'authorization_servers' => [$this->issuer()], 'scopes_supported' => [], 'bearer_methods_supported' => ['header'], 'resource_name' => 'Emailing'];
    }

    public function server(): array
    {
        $issuer = $this->issuer();
        $metadata = ['issuer' => $issuer, 'authorization_endpoint' => $issuer.'/oauth/authorize', 'token_endpoint' => $issuer.'/oauth/token', 'revocation_endpoint' => $issuer.'/oauth/revoke', 'response_types_supported' => ['code'], 'grant_types_supported' => ['authorization_code'], 'code_challenge_methods_supported' => ['S256'], 'token_endpoint_auth_methods_supported' => ['none'], 'revocation_endpoint_auth_methods_supported' => ['none'], 'authorization_response_iss_parameter_supported' => true, 'client_id_metadata_document_supported' => false, 'scopes_supported' => DelegatedContext::scopes()];
        if (config('mcp.dynamic_registration')) {
            $metadata['registration_endpoint'] = $issuer.'/oauth/register';
        }

        return $metadata;
    }
}
