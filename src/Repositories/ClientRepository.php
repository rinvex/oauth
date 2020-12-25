<?php

declare(strict_types=1);

namespace Rinvex\OAuth\Repositories;

use Rinvex\OAuth\Bridge\Client;
use League\OAuth2\Server\Repositories\ClientRepositoryInterface;

class ClientRepository implements ClientRepositoryInterface
{
    /**
     * {@inheritdoc}
     */
    public function getClientEntity($clientIdentifier)
    {
        $client = app('rinvex.oauth.client')->where('id', $clientIdentifier)->first();

        $record = $client && ! $client->is_revoked ? $client : null;

        if (! $record) {
            return;
        }

        return new Client(
            $clientIdentifier,
            $record->name,
            $record->redirect,
            $record->isConfidential(),
            $record->provider
        );
    }

    /**
     * {@inheritdoc}
     */
    public function validateClient($clientIdentifier, $clientSecret, $grantType)
    {
        // First, we will verify that the client exists and is authorized to create personal
        // access tokens. Generally personal access tokens are only generated by the user
        // from the main interface. We'll only let certain clients generate the tokens.
        $client = app('rinvex.oauth.client')->where('id', $clientIdentifier)->first();

        $record = $client && ! $client->is_revoked ? $client : null;

        if (! $record || ! $this->handlesGrant($record, $grantType)) {
            return false;
        }

        return ! $record->isConfidential() || password_verify((string) $clientSecret, $record->secret);
    }

    /**
     * Determine if the given client can handle the given grant type.
     *
     * @param \Rinvex\OAuth\Models\Client $record
     * @param string                      $grantType
     *
     * @return bool
     */
    protected function handlesGrant($record, $grantType)
    {
        if (is_array($record->grant_types) && ! in_array($grantType, $record->grant_types)) {
            return false;
        }

        switch ($grantType) {
            case 'authorization_code':
                return ! $record->firstParty();
            case 'personal_access':
                return $record->grant_type === 'personal_access' && $record->isConfidential();
            case 'password':
                return $record->grant_type === 'password';
            case 'client_credentials':
                return $record->isConfidential();
            default:
                return true;
        }
    }
}
