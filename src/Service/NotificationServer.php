<?php

namespace App\Service;

use Exception;
use GuzzleHttp\Psr7\Request;
use Lcobucci\JWT\Parser;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Token;
use Ratchet\ConnectionInterface;
use Ratchet\WebSocket\MessageComponentInterface;
use Ratchet\WebSocket\WsConnection;

class NotificationServer implements MessageComponentInterface
{
    protected \SplObjectStorage $clients;

    public function __construct()
    {
        $this->clients = new \SplObjectStorage();
    }

    /**
     * @param string $token
     * @return \Lcobucci\JWT\Token
     * @throws Exception
     */
    public function verifyToken(string $token): Token
    {
        $token = (new Parser())->parse((string) $token);
        if (!$token->verify(new Sha256(), $_ENV['MY_SIGNING_SECRET'])) {
            throw new Exception("Token verification failed");
        }
        return $token;
    }

    public function onOpen(ConnectionInterface /** @var $conn WsConnection  */ $conn)
    {
        /** @var $request Request */
        $request = $conn->httpRequest;
        $query = $request->getUri()->getQuery();
        parse_str($query, $params);

        if (empty($params['type']) || empty($params['auth_token'])) {
            $conn->close();
            return;
        }

        if ($params['type'] == 'server') {
            if ($params['auth_token'] != $_ENV['MY_SIGNING_SECRET']) {
                $conn->close();
                return;
            }
            return;
        }

        try {
            $token = $this->verifyToken($params['auth_token']);
        } catch (Exception $e) {
            $conn->close();
            return;
        }

        $data = [
            'board' => $token->getClaim('board_id'),
            'account' => $token->getClaim('account_id'),
            'user' => $token->getClaim('user_id'),
            'owner' => $token->getClaim('owner_id'),
            'chat_account' => $token->getClaim('chat_account_id')
        ];

        $this->clients->attach($conn, $data);
    }

    public function onMessage(ConnectionInterface /** @var $from WsConnection  */  $from, $msg)
    {
        $msg = json_decode($msg, true);

        if ($this->clients->contains($from)) {
            return;
        }

        $this->sendClientMessage($msg['to'], $msg['id'], $msg['message']);
    }

    public function sendClientMessage($to, $id, $message)
    {
        foreach ($this->clients as $client) {
            /** @var ConnectionInterface $client */
            $info  = $this->clients->getInfo();
            echo json_encode($info);
            if ($info[$to] == $id) {
                $client->send(json_encode($message));
            }
        }
    }

    public function onClose(ConnectionInterface /** @var $conn WsConnection  */  $conn)
    {
        if ($this->clients->contains($conn)) {
            $this->clients->detach($conn);
            return;
        }
    }

    public function onError(ConnectionInterface $conn, \Exception $e)
    {
        echo "Error: " . $e->getMessage() . "\n";
        $conn->close();
    }
}
