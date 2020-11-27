<?php

declare(strict_types=1);

use DI\ContainerBuilder;
use Monolog\Logger;

date_default_timezone_set('utc');

return function (ContainerBuilder $containerBuilder) {
    // Global Settings Object
    $containerBuilder->addDefinitions([
        'settings' => [
            'displayErrorDetails' => $_ENV['IS_ENV_DEV'], // Should be set to false in production
            'logger' => [
                'name' => 'monday-cs',
                'path' => isset($_ENV['docker']) ? 'php://stdout' : __DIR__ . '/../logs/app.log',
                'level' => Logger::DEBUG,
            ],
            'mysql' => [
                'host' => $_ENV['DATABASE_HOST'],
                'username' => $_ENV['DATABASE_USER'],
                'password' => $_ENV['DATABASE_PASSWORD'],
                'db' => $_ENV['DATABASE_NAME'],
                'charset' => 'utf8mb4'
            ],
            'view' => [
                'templates' => APP_ROOT . '/templates',
                'cache' => APP_ROOT . '/var/cache/twig'
            ],
            'jwt' => [
                'issued_by' => 'monday-cs',
                'permitted_for' => 'monday-cs',
                'identified_by' => 'monday-cs',
                'signing_secret' => $_ENV['MY_SIGNING_SECRET'],
                'expire_after' => (3600 * 60 * 12 * 30)
            ]
        ],
    ]);
};
