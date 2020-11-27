<?php

namespace App\Service;

use Exception;
use GuzzleHttp\Client;
use Lcobucci\JWT\Parser;
use Lcobucci\JWT\Signer\Hmac\Sha256;

class MondayClient extends GraphQLClient
{
    public const MONDAY_API_URL = 'https://api.monday.com/v2';
    public const MONDAY_OAUTH_URL = 'https://auth.monday.com/oauth2/authorize';
    public const MONDAY_OAUTH_TOKEN_URL = 'https://auth.monday.com/oauth2/token';

    public function __construct()
    {
        parent::__construct(self::MONDAY_API_URL);
    }

    /**
     * @param string $token
     * @param string $secret
     * @return \Lcobucci\JWT\Token
     * @throws Exception
     */
    public function verifyToken(string $token, string $secret)
    {
        $token = (new Parser())->parse((string) $token);
        if (!$token->verify(new Sha256(), $secret)) {
            throw new Exception("Token verification failed");
        };
        return $token;
    }

    public function getOAuthToken(string $code, string $clientId, string $clientSecret)
    {
        $guzzle = new Client();
        $response = $guzzle->request('POST', self::MONDAY_OAUTH_TOKEN_URL, [
            'form_params' => [
                'code' => $code,
                'client_id' => $clientId,
                'client_secret' => $clientSecret
            ]
        ]);

        if ($response->getStatusCode() === 200) {
            return json_decode($response->getBody(), true);
        }

        throw new Exception('Cannot get access token from monday.com');
    }

    public function trigger(string $url, array $data = [])
    {
        return $this->client->request('POST', $url, [
            'body' => json_encode(['trigger' => ['outputFields' => $data]]),
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => $this->authHeader
            ]
        ]);
    }
}
