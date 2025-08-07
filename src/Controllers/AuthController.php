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
use Flarum\Forum\Auth\ResponseFactory;
use Flarum\Http\Exception\RouteNotFoundException;
use Flarum\User\LoginProvider;
use Flarum\User\User;
use LSTechNeighbor\TCPOIDC\Controller;
use LSTechNeighbor\TCPOIDC\Events\SettingSuggestions;
use LSTechNeighbor\TCPOIDC\Provider;
use Illuminate\Support\Arr;
use League\OAuth2\Client\Provider\AbstractProvider;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class AuthController extends Controller
{
    /**
     * @var ResponseFactory
     */
    protected $response;

    public function __construct(ResponseFactory $response)
    {
        $this->response = $response;
    }

    protected function getProviderName(): string
    {
        return 'tcp';
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        error_log("TCP OIDC: AuthController handle method called!");
        
        $queryParams = $request->getQueryParams();
        $session = $request->getAttribute('session');
        $routeParams = $request->getAttribute('routeParameters', []);

        $providerName = 'tcp'; // Hardcoded for TCP route
        
        // Debug logging
        error_log("TCP OIDC: AuthController called with provider: " . $providerName);
        error_log("TCP OIDC: Request URI: " . $request->getUri());
        error_log("TCP OIDC: Route params: " . print_r($routeParams, true));

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
            error_log("TCP OIDC: Provider not found for: " . $providerName);
            throw new RouteNotFoundException();
        }
        
        error_log("TCP OIDC: Provider found: " . $provider->name());

        // Check if this is a callback
        if (isset($queryParams['code'])) {
            return $this->handleCallback($request, $provider);
        }

        // Start OAuth flow
        return $this->startAuthFlow($request, $provider);
    }

    protected function startAuthFlow(ServerRequestInterface $request, Provider $provider): ResponseInterface
    {
        try {
            $redirectUri = $this->getRedirectUri($request, $provider->name());
            $oauthProvider = $provider->provider($redirectUri);

            $authUrl = $oauthProvider->getAuthorizationUrl([
                'scope' => 'openid profile email'
            ]);

            header('Location: ' . $authUrl);
            exit;
        } catch (\Exception $e) {
            // If there's an error (like missing configuration), redirect to forum with error
            $forumUrl = (string) $request->getUri()->withPath('/');
            $errorUrl = $forumUrl . '?oauth_error=configuration';
            header('Location: ' . $errorUrl);
            exit;
        }
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

        error_log("TCP OIDC: User data received: " . print_r($user->toArray(), true));

        // Use Flarum's OAuth response factory to handle the registration/login
        return $this->response->make(
            $provider->name(),
            $user->getId(),
            function (Registration $registration) use ($user, $provider) {
                $this->setSuggestions($registration, $user, $provider);
            }
        );
    }

    protected function setSuggestions(Registration $registration, $user, Provider $provider)
    {
        error_log("TCP OIDC: Setting registration suggestions");
        
        // Get email from user data
        $email = $user->getEmail();
        
        if (empty($email)) {
            // Try to get email from user array data
            $userData = $user->toArray();
            $email = $userData['email'] ?? null;
        }

        if (empty($email)) {
            error_log("TCP OIDC: No email found in user data");
            throw new \Exception('No email address provided by TCP OIDC provider');
        }

        error_log("TCP OIDC: Using email: " . $email);

        // Get username from user data
        $username = $user->getName() ?? $user->getNickname() ?? '';
        
        // Get avatar if available
        $avatar = $user->getAvatar() ?? '';

        $registration
            ->provideTrustedEmail($email)
            ->suggestUsername($username)
            ->provideAvatar($avatar)
            ->setPayload($user->toArray());
    }

    protected function getRedirectUri(ServerRequestInterface $request, string $providerName): string
    {
        $uri = $request->getUri();
        return (string) $uri->withPath("/auth/{$providerName}");
    }
} 