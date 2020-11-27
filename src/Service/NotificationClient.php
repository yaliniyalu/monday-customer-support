<?php

namespace App\Service;

class NotificationClient
{
    private \WebSocket\Client $client;

    public function __construct()
    {
        $url = $_ENV['WEBSOCKET_SERVER_URL'] . "?type=server&auth_token=" . $_ENV['MY_SIGNING_SECRET'];
        $this->client = new \WebSocket\Client($url);
    }

    public function send(array $data)
    {
        $this->client->send(json_encode($data));
    }

    public function close()
    {
        $this->client->close();
    }
}
