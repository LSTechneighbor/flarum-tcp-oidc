<?php

/*
 * This file is part of lstechneighbor/flarum-tcp-oidc.
 *
 * Copyright (c) Larry Squitieri.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace LSTechNeighbor\TCPOIDC\Controllers;

use Flarum\Forum\Auth\Registration;
use Flarum\Http\Exception\RouteNotFoundException;
use LSTechNeighbor\TCPOIDC\Controller;
use LSTechNeighbor\TCPOIDC\Events\SettingSuggestions;
use LSTechNeighbor\TCPOIDC\Provider;
use Illuminate\Support\Arr;
use League\OAuth2\Client\Provider\AbstractProvider;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class AuthController implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $queryParams = $request->getQueryParams();
        $session = $request->getAttribute('session');
        $routeParams = $request->getAttribute('routeParameters', []);

        $providerName = $routeParams['provider'] ?? 'tcp';

        // Get the provider instance
        $container = app();
        $providers = $container->tagged('lstechneighbor-tcp-oidc.providers');
        
        $provider = null;
        foreach ($providers as $p) {
            if ($p->name() === $providerName) {
                $provider = $p;
                break;
            }
        }

        if (!$provider) {
            throw new RouteNotFoundException();
        }

        // Check if this is a callback
        if (isset($queryParams['code'])) {
            return $this->handleCallback($request, $provider);
        }

        // Start OAuth flow
        return $this->startAuthFlow($request, $provider);
    }

    protected function startAuthFlow(ServerRequestInterface $request, Provider $provider): ResponseInterface
    {
        $redirectUri = $this->getRedirectUri($request, $provider->name());
        $oauthProvider = $provider->provider($redirectUri);

        $authUrl = $oauthProvider->getAuthorizationUrl([
            'scope' => 'openid profile email'
        ]);

        return new \Zend\Diactoros\Response\RedirectResponse($authUrl);
    }

    protected function handleCallback(ServerRequestInterface $request, Provider $provider): ResponseInterface
    {
        $queryParams = $request->getQueryParams();
        $code = $queryParams['code'] ?? null;

        if (!$code) {
            throw new \Exception('Authorization code not received');
        }

        $redirectUri = $this->getRedirectUri($request, $provider->name());
        $oauthProvider = $provider->provider($redirectUri);

        // Exchange code for token
        $token = $oauthProvider->getAccessToken('authorization_code', [
            'code' => $code
        ]);

        // Get user info
        $user = $oauthProvider->getResourceOwner($token);

        // Create or update user
        return $this->createOrUpdateUser($user, $provider, $request);
    }

    protected function createOrUpdateUser($user, Provider $provider, ServerRequestInterface $request): ResponseInterface
    {
        // This is a simplified version - you may need to implement user creation logic
        // based on your specific requirements
        
        $registration = new Registration();
        $provider->suggestions($registration, $user, '');

        // For now, redirect to forum
        return new \Zend\Diactoros\Response\RedirectResponse('/');
    }

    protected function getRedirectUri(ServerRequestInterface $request, string $providerName): string
    {
        $uri = $request->getUri();
        return (string) $uri->withPath("/auth/{$providerName}");
    }
} 