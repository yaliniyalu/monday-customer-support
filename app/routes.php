<?php

declare(strict_types=1);

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;
use Slim\Interfaces\RouteCollectorProxyInterface as Group;

return function (App $app) {

    $app->get('/', function (Request $request, Response $response) {
        $response->getBody()->write('Hello world!');
        return $response;
    });

    $app->group('/oauth', function (Group $group) {
        $group->group('/monday', function (Group $group) {
            $group->get('/redirect', \App\Application\Actions\Authorization\MondayOAuthRedirect::class);
            $group->get('/callback', \App\Application\Actions\Authorization\MondayOAuthCallback::class);
        });

        $group->get('/accounts', \App\Application\Actions\Authorization\SelectOAuthAccounts::class);

        $group->group('/{provider}', function (Group $group) {
            $group->get('/redirect', \App\Application\Actions\Authorization\OAuthRedirect::class);
            $group->get('/callback', \App\Application\Actions\Authorization\OAuthCallback::class);
        })->add(\App\Application\Middleware\LoadOAuthProvider::class);
    })->add(\App\Application\Middleware\AuthenticateWithMondayOAuthToken::class);

    $app->post('/webhook/gmail/message', \App\Application\Actions\Webhook\OnMessageGmail::class);
    $app->post('/webhook/outlook/message', \App\Application\Actions\Webhook\OnMessageOutlook::class);

    $app->post('/board/{boardId}/generate/token', \App\Application\Actions\User\GenerateToken::class)
        ->add(\App\Application\Middleware\AuthenticateWithMondaySessionToken::class);

    $app->group('/board/{boardId}', function (Group $group) {
        $group->group('/customer/{encodedCustomerId}', function (Group $group) {
            $group->group('/message', function (Group $group) {
                // edit group
                $group->group('', function (Group $group) {
                    $group->post('/send', \App\Application\Actions\Message\SendMessage::class);
                    $group->post('/{messageId}/mark/read', \App\Application\Actions\Message\MarkMessageAsRead::class);
                    $group->post('/mark/read', \App\Application\Actions\Message\MarkMultipleMessageAsRead::class);
                })
                    ->add(\App\Application\Middleware\AuthorizeBoardEdit::class);

                $group->get(
                    '/{messageId}/attachment/{attachmentId}',
                    \App\Application\Actions\Message\GetAttachment::class
                )->add(\App\Application\Middleware\AuthorizeBoardAccess::class);
            });

            $group->group('/chat/{chatId}', function (Group $group) {
                $group->group('/message', function (Group $group) {
                    $group->get('', \App\Application\Actions\Message\ListMessagesByChat::class);
                    $group
                        ->post('/send', \App\Application\Actions\Message\ReplyMessage::class)
                        ->add(\App\Application\Middleware\AuthorizeBoardEdit::class);
                });
            });
        })
            ->add(\App\Application\Middleware\DecodeCustomerId::class)
            ->add(\App\Application\Middleware\LoadChatProvider::class);
    })
        ->add(\App\Application\Middleware\AuthorizeBoardAccess::class)
        ->add(\App\Application\Middleware\AuthenticateWithUserAuthToken::class);


    $app->group('/monday/trigger', function (Group $group) {
        $group->group('/message-received', function (Group $group) {
            $group->post('/subscribe', \App\Application\Actions\MondayTrigger\MessageReceived\Subscribe::class);
            $group->post('/unsubscribe', \App\Application\Actions\MondayTrigger\MessageReceived\Unsubscribe::class);
        });
    })->add(\App\Application\Middleware\AuthenticateWithMondayAuthToken::class);

    $app->group('/monday/action', function (Group $group) {
        $group->post('/create-item', \App\Application\Actions\MondayAction\CreateItem::class);
        $group->post('/set-label', \App\Application\Actions\MondayAction\SetLabel::class);
    })->add(\App\Application\Middleware\AuthenticateWithMondayAuthToken::class);
};
