<?php

declare(strict_types=1);

use DI\ContainerBuilder;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Monolog\Processor\UidProcessor;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Slim\Views\Twig;

return function (ContainerBuilder $containerBuilder) {
    $containerBuilder->addDefinitions([
        LoggerInterface::class => function (ContainerInterface $c) {
            $settings = $c->get('settings');

            $loggerSettings = $settings['logger'];
            $logger = new Logger($loggerSettings['name']);

            $processor = new UidProcessor();
            $logger->pushProcessor($processor);

            $handler = new StreamHandler($loggerSettings['path'], $loggerSettings['level']);
            $logger->pushHandler($handler);

            return $logger;
        },

        MysqliDb::class => function (ContainerInterface $c) {
            $settings = $c->get('settings')['mysql'];
            return new MysqliDb($settings);
        },

        \Google\Client::class => function (ContainerInterface $c) {
            return new Google_Client();
        },

        \League\OAuth2\Client\Provider\Google::class => function (ContainerInterface $c) {
            return new \League\OAuth2\Client\Provider\Google([
                'clientId'     => $_ENV['GOOGLE_CLIENT_ID'],
                'clientSecret' => $_ENV['GOOGLE_CLIENT_SECRET'],
                'redirectUri'  => $_ENV['GOOGLE_REDIRECT_URI'],
                'accessType'   => 'offline',
                'scopes'       => [
                        \Google_Service_Gmail::GMAIL_SEND,
                        \Google_Service_Gmail::GMAIL_LABELS,
                        \Google_Service_Gmail::GMAIL_MODIFY,
                        \Google_Service_Oauth2::USERINFO_EMAIL,
                        \Google_Service_Oauth2::USERINFO_PROFILE,
                    ]
            ]);
        },

        \Stevenmaguire\OAuth2\Client\Provider\Microsoft::class => function (ContainerInterface $c) {
            $microsoft = new \Stevenmaguire\OAuth2\Client\Provider\Microsoft([
                'clientId'     => $_ENV['MICROSOFT_CLIENT_ID'],
                'clientSecret' => $_ENV['MICROSOFT_CLIENT_SECRET'],
                'redirectUri'  => $_ENV['MICROSOFT_REDIRECT_URI'],

                'urlAuthorize'              => 'https://login.microsoftonline.com/common/oauth2/v2.0/authorize',
                'urlAccessToken'            => 'https://login.microsoftonline.com/common/oauth2/v2.0/token',
                'urlResourceOwnerDetails'   => 'https://outlook.office.com/api/v1.0/me',
            ]);

            $microsoft->defaultScopes = [
                'openid',
                'profile',
                'offline_access',
                'user.read',
                'mailboxsettings.read',
                'Mail.ReadWrite',
                'Mail.Send'
            ];
            return $microsoft;
        },

        Twig::class => function (ContainerInterface $c) {
            $settings = $c->get('settings')['view'];
            return Twig::create($settings['templates'], ['cache' => $settings['cache']]);
        },

        \App\Domain\JwtConfig::class => function (ContainerInterface $c) {
            $settings = $c->get('settings')['jwt'];

            $config = new \App\Domain\JwtConfig();
            $config->issuedBy      = $settings['issued_by'];
            $config->permittedFor  = $settings['permitted_for'];
            $config->identifiedBy  = $settings['identified_by'];
            $config->expireAfter   = $settings['expire_after'];
            $config->signingSecret = $settings['signing_secret'];

            return $config;
        }
    ]);
};
