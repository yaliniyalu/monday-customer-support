<?php

namespace App\Service;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class GraphQLClient
{
    protected string $endpoint;
    protected string $authHeader;
    protected Client $client;

    public function __construct(string $endpoint)
    {
        $this->endpoint = $endpoint;
        $this->client = new Client();
    }

    public function setToken(string $token, string $type = 'Bearer')
    {
        if ($token) {
            $this->authHeader = $type . ' ' . $token;
        } else {
            $this->authHeader = '';
        }
    }

    /**
     * @param $query
     * @param array $variables
     * @throws GuzzleException
     */
    public function query($query, $variables = [])
    {
        return $this->client->request('POST', $this->endpoint, [
            'body' => json_encode(['query' => $query, 'variables' => $variables]),
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => $this->authHeader
            ]
        ]);
    }
}
