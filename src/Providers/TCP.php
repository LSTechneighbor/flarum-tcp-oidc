<?php

/*
 * This file is part of lstechneighbor/flarum-tcp-oidc.
 *
 * Copyright (c) Larry Squitieri.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace LSTechNeighbor\TCPOIDC\Providers;

use Flarum\Forum\Auth\Registration;
use LSTechNeighbor\TCPOIDC\Provider;
use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Provider\GenericProvider;

class TCP extends Provider
{
    public function name(): string
    {
        return 'tcp';
    }

    public function link(): string
    {
        return 'https://github.com/lstechneighbor/flarum-tcp-oidc';
    }

    public function link(): string
    {
        return 'https://github.com/lstechneighbor/flarum-tcp-oidc';
    }

    public function fields(): array
    {
        return [
            'tcp_url'       => 'required',
            'client_id'     => 'required',
            'client_secret' => 'required',
        ];
    }

    public function icon(): string
    {
        return 'fas fa-key';
    }

    public function provider(string $redirectUri): AbstractProvider
    {
        $tcpUrl = $this->getSetting('tcp_url');
        
        return new GenericProvider([
            'clientId'                => $this->getSetting('client_id'),
            'clientSecret'            => $this->getSetting('client_secret'),
            'redirectUri'             => $redirectUri,
            'urlAuthorize'            => $tcpUrl . '/api/oidc/authorize',
            'urlAccessToken'          => $tcpUrl . '/api/oidc/token',
            'urlResourceOwnerDetails' => $tcpUrl . '/api/oidc/userinfo',
            'scopes'                  => 'openid profile email',
        ]);
    }

    public function suggestions(Registration $registration, $user, string $token)
    {
        $this->verifyEmail($email = $user->getEmail());

        $registration
            ->provideTrustedEmail($email)
            ->suggestUsername($user->getName())
            ->provideAvatar($user->getAvatar())
            ->setPayload($user->toArray());
    }

    protected function getSetting($key): string
    {
        return $this->settings->get("lstechneighbor-tcp-oidc.{$this->name()}.{$key}") ?? '';
    }

    public function enabled()
    {
        return $this->settings->get("lstechneighbor-tcp-oidc.{$this->name()}");
    }
}
