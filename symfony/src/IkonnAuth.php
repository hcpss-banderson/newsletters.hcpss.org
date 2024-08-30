<?php

namespace App;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\HandlerStack;
use Sainsburys\Guzzle\Oauth2\AccessToken;
use Sainsburys\Guzzle\Oauth2\GrantType\ClientCredentials;
use Sainsburys\Guzzle\Oauth2\GrantType\GrantTypeBase;
use Sainsburys\Guzzle\Oauth2\Middleware\OAuthMiddleware;

class IkonnAuth {

    protected $token_file = '/var/www/symfony/credentials/ikonn_token.json';

    /**
     * @return \Sainsburys\Guzzle\Oauth2\AccessToken|null
     */
    protected function authorize(): ?AccessToken {
        $ikonn_base = 'https://api.hocoschools.org';
        $client_id = getenv('IKONN_CLIENT_ID');
        $client_secret = getenv('IKONN_CLIENT_SECRET');

        $handlerStack = HandlerStack::create();
        $client = new GuzzleClient([
            'handler' => $handlerStack,
            'base_uri' => $ikonn_base,
            'auth' => 'oauth2',
        ]);
        $middleware = new OAuthMiddleware($client, new ClientCredentials($client, [
            GrantTypeBase::CONFIG_CLIENT_ID => $client_id,
            GrantTypeBase::CONFIG_CLIENT_SECRET => $client_secret,
            GrantTypeBase::CONFIG_TOKEN_URL => 'oauth/token',
            'scope' => 'school_importer',
        ]));

        return $middleware->getAccessToken();
    }

    /**
     * @param \Sainsburys\Guzzle\Oauth2\AccessToken $token
     * @return void
     * @throws \JsonException
     */
    protected function writeToken(AccessToken $token): void {
        file_put_contents($this->token_file, json_encode([
            'token' => $token->getToken(),
            'expires' => $token->getExpires()->getTimestamp(),
        ], JSON_THROW_ON_ERROR));
    }

    /**
     * @return \Sainsburys\Guzzle\Oauth2\AccessToken|null
     */
    protected function loadToken(): ?AccessToken {
        if (!file_exists($this->token_file)) {
            return NULL;
        }

        $data = json_decode(file_get_contents($this->token_file), TRUE);
        return new AccessToken(
            $data['token'],
            'Bearer',
            ['expires' => $data['expires']]
        );
    }

    /**
     * @return \Sainsburys\Guzzle\Oauth2\AccessToken
     * @throws \JsonException
     */
    public function getToken(): AccessToken {
        $token = $this->loadToken();
        if (!$token || $token->isExpired()) {
            $token = $this->authorize();
            $this->writeToken($token);
        }

        return $token;
    }
}
