<?php

include_once __DIR__ . '/../vendor/autoload.php';
const APP_ROOT = __DIR__ . '/..';

$dotenv = Dotenv\Dotenv::createImmutable(APP_ROOT);
$dotenv->load();

use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;

$app = new HttpServer(new WsServer(new \App\Service\NotificationServer()));
$loop = \React\EventLoop\Factory::create();
$socketServer = new \React\Socket\Server('0.0.0.0:' . $_ENV['WEBSOCKET_SERVER_PORT'], $loop);

if ($_ENV['WEBSOCKET_SECURE']) {
    $socketServer = new \React\Socket\SecureServer($socketServer, $loop, [
        'local_cert' => $_ENV['SSL_CERT'],
        'local_pk' => $_ENV['SSL_PK'],
        'verify_peer' => false
    ]);
}

$ioServer = new \Ratchet\Server\IoServer($app, $socketServer, $loop);
$ioServer->run();
