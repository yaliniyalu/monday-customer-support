<?php

namespace App\Application\Actions\Authorization;

use App\Application\Actions\Action;
use Lcobucci\JWT\Token;
use League\OAuth2\Client\Provider\AbstractProvider;
use Psr\Http\Message\ResponseInterface as Response;

class OAuthRedirect extends Action
{
    protected function action(): Response
    {
        /** @var Token $token */
        $token = $this->request->getAttribute('mondayToken');

        /** @var AbstractProvider $oauthClient */
        $oauthClient = $this->request->getAttribute('oauthClient');

        $provider = $this->args['provider'];

        switch ($provider) {
            case 'google':
                $options = [
                    'accessType' => 'offline',
                    'prompt'     => 'consent'
                ];
                break;

            case 'microsoft':
                $options = [
                    'prompt'     => 'consent',
                ];
        }

        $options['state'] = (string) $token;

        $url = $oauthClient->getAuthorizationUrl($options);
        return $this->redirect($url);
    }
}
