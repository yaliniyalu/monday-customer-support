<?php

namespace App\Service;

use App\Domain\Chat\Provider\Gmail;
use App\Domain\Chat\Provider\Outlook;
use App\Domain\Chat\Provider\Provider;
use App\Domain\ChatAccount;
use DI\Container;
use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Token\AccessTokenInterface;
use Microsoft\Graph\Graph;

class ChatProviderService
{
    private Container $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public function get(ChatAccount $account): Provider
    {
        switch ($account->getType()) {
            case 'gmail':
                return $this->getGmail($account);
            case 'outlook':
                return $this->getOutlook($account);
            default:
                throw new \Exception();
        }
    }

    public function getByType(string $type, string $accessToken): Provider
    {
        switch ($type) {
            case 'gmail':
                return $this->getGmailWithAccessToken($accessToken);
            case 'outlook':
                return $this->getOutlookWithAccessToken($accessToken);
            default:
                throw new \Exception();
        }
    }

    public function fetchAccessToken(ChatAccount $account): AccessTokenInterface
    {
        switch ($account->getType()) {
            case 'gmail':
                $provider = $this->container->get(\League\OAuth2\Client\Provider\Google::class);
                break;

            case 'outlook':
                $provider = $this->container->get(\Stevenmaguire\OAuth2\Client\Provider\Microsoft::class);
                break;

            default:
                throw new \Exception();
        }

        return $provider->getAccessToken('refresh_token', [
            'refresh_token' => $account->getRefreshToken()
        ]);
    }

    private function getGmailWithAccessToken($accessToken): Provider
    {
        /** @var \Google_Client */
        $client = $this->container->get(\Google\Client::class);
        $client->setAccessToken($accessToken);
        return new Gmail($client);
    }

    private function getOutlookWithAccessToken(string $accessToken): Provider
    {
        $graph = new Graph();
        $graph->setAccessToken($accessToken);
        return new Outlook($graph);
    }

    private function getGmail(ChatAccount $account): Provider
    {
        $provider = $this->container->get(\League\OAuth2\Client\Provider\Google::class);
        $token = $this->getOAuthAccessToken($provider, $account);
        return $this->getGmailWithAccessToken($token);
    }

    private function getOutlook(ChatAccount $account)
    {
        $provider = $this->container->get(\Stevenmaguire\OAuth2\Client\Provider\Microsoft::class);
        $token = $this->getOAuthAccessToken($provider, $account);
        return $this->getOutlookWithAccessToken($token);
    }

    private function getOAuthAccessToken(AbstractProvider $provider, ChatAccount $account)
    {
        $now = time() + 300;
        if ($account->getExpiry() <= $now) {
            $newToken = $provider->getAccessToken('refresh_token', [
                'refresh_token' => $account->getRefreshToken()
            ]);
            $account->setAccessToken($newToken->getToken());
            $account->setExpiry($newToken->getExpires());

            /** @var ChatAccountService $service */
            $service = $this->container->get(ChatAccountService::class);
            $service->update($account);
        }

        return $account->getAccessToken();
    }
}
