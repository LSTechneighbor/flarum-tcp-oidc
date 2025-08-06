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
use Flarum\Settings\SettingsRepositoryInterface;
use LSTechNeighbor\TCPOIDC\Provider;
use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Provider\GenericProvider;

class TCP extends Provider
{
    /**
     * @var SettingsRepositoryInterface
     */
    protected $settings;

    public function __construct(SettingsRepositoryInterface $settings)
    {
        $this->settings = $settings;
    }

    public function name(): string
    {
        return 'tcp';
    }

    public function link(): string
    {
        return 'https://github.com/lstechneighbor/flarum-tcp-oidc';
    }

    public function fields(): array
    {
        return [
            'url'           => 'required',
            'client_id'     => 'required',
            'client_secret' => 'required',
        ];
    }

    public function icon(): string
    {
        return 'tcp-text';
    }

    public function provider(string $redirectUri): AbstractProvider
    {
        $tcpUrl = $this->getSetting('url');
        
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
            ->setPayload($user->toArray());

        $avatar = $user->getAvatar();
        if ($avatar) {
            $registration->provideAvatar($avatar);
        }
    }

    protected function getSetting($key): string
    {
        return $this->settings->get("lstechneighbor-tcp-oidc.{$this->name()}.{$key}") ?? '';
    }

    public function enabled()
    {
        $enabled = $this->settings->get("lstechneighbor-tcp-oidc.{$this->name()}");
        return $enabled;
    }
} 