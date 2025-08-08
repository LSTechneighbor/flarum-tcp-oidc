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
use LSTechNeighbor\TCPOIDC\Events\SettingSuggestions;
use LSTechNeighbor\TCPOIDC\Provider;
use Illuminate\Support\Arr;
use League\OAuth2\Client\Provider\AbstractProvider;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class AuthController implements RequestHandlerInterface
{
    /**
     * @var ResponseFactory
     */
    protected $response;

    public function __construct(ResponseFactory $response)
    {
        $this->response = $response;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $queryParams = $request->getQueryParams();
        $session = $request->getAttribute('session');
        $routeParams = $request->getAttribute('routeParameters', []);

        $providerName = 'tcp'; // Hardcoded for TCP route
        
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
        try {
            $redirectUri = $this->getRedirectUri($request, $provider->name());
            $oauthProvider = $provider->provider($redirectUri);

            $authUrl = $oauthProvider->getAuthorizationUrl([
                'scope' => 'openid profile email'
            ]);

            header('Location: ' . $authUrl);
            exit;
        } catch (\Exception $e) {
            error_log("TCP OIDC: Configuration error - " . $e->getMessage());
            // If there's an error (like missing configuration), redirect to forum with error
            $forumUrl = (string) $request->getUri()->withPath('/');
            $errorUrl = $forumUrl . '?oauth_error=configuration';
            header('Location: ' . $errorUrl);
            exit;
        }
    }

    protected function handleCallback(ServerRequestInterface $request, Provider $provider): ResponseInterface
    {
        try {
            $queryParams = $request->getQueryParams();
            $code = $queryParams['code'] ?? null;
            $state = $queryParams['state'] ?? null;

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
            $userData = $user->toArray();
            
            // Get user data array to access email and name
            $email = $userData['email'] ?? $userData['email_address'] ?? $userData['mail'] ?? null;
            $name = $userData['name'] ?? $userData['given_name'] ?? $userData['nickname'] ?? null;
            
            // Log successful user authentication
            error_log("TCP OIDC: User authenticated successfully - Email: " . ($email ?? 'null') . ", Name: " . ($name ?? 'null'));
            
            try {
                // Get parameters for response->make
                $providerName = $provider->name();
                
                // Get user ID from user data array (OpenID Connect uses 'sub' field)
                $userId = $userData['sub'] ?? $userData['id'] ?? $user->getId();
                
                $response = $this->response->make(
                    $providerName,
                    $userId,
                    function (Registration $registration) use ($user, $provider) {
                        $this->setSuggestions($registration, $user, $provider);
                    }
                );
                
                // Log successful user creation/login
                error_log("TCP OIDC: User registration/login completed successfully for: " . ($email ?? 'unknown'));
                return $response;
            } catch (\Exception $e) {
                error_log("TCP OIDC: User registration failed - " . $e->getMessage());
                throw $e;
            }
        } catch (\Exception $e) {
            error_log("TCP OIDC: Authentication failed - " . $e->getMessage());
            
            // Redirect to forum with error
            $forumUrl = (string) $request->getUri()->withPath('/');
            $errorUrl = $forumUrl . '?oauth_error=callback&message=' . urlencode($e->getMessage());
            header('Location: ' . $errorUrl);
            exit;
        }
    }

    protected function setSuggestions(Registration $registration, $user, Provider $provider)
    {
        // Get user data array to access email and name
        $userData = $user->toArray();
        
        // Get email from user data
        $email = $userData['email'] ?? $userData['email_address'] ?? $userData['mail'] ?? null;

        if (empty($email)) {
            throw new \Exception('No email address provided by TCP OIDC provider');
        }

        // Get username from user data (use TCP Name field for Flarum username)
        $username = $userData['name'] ?? $userData['given_name'] ?? $userData['nickname'] ?? $userData['username'] ?? '';
        
        // Get organization name from email domain or user data
        $orgName = $userData['org'] ?? $userData['organization'] ?? $userData['org_name'] ?? null;
        if (empty($orgName) && !empty($email)) {
            // Extract org from email domain (e.g., ljsquitieri@gmail.com -> gmail.com)
            $emailParts = explode('@', $email);
            if (count($emailParts) > 1) {
                $orgName = $emailParts[1];
            }
        }
        
        // Get avatar if available
        $avatar = $userData['picture'] ?? $userData['avatar'] ?? '';

        try {
            // Add org name to the payload
            $payload = $user->toArray();
            if (!empty($orgName)) {
                $payload['org_name'] = $orgName;
            }
            
            $registration
                ->provideTrustedEmail($email)
                ->suggestUsername($username)
                ->setPayload($payload);
            
            // Only provide avatar if it's a valid URL
            if (!empty($avatar) && filter_var($avatar, FILTER_VALIDATE_URL)) {
                $registration->provideAvatar($avatar);
            }
        } catch (\Exception $e) {
            error_log("TCP OIDC: Failed to set registration suggestions - " . $e->getMessage());
            throw $e;
        }
    }

    protected function getRedirectUri(ServerRequestInterface $request, string $providerName): string
    {
        $uri = $request->getUri();
        // Return the clean base URI without query parameters
        return (string) $uri->withPath("/auth/{$providerName}")->withQuery('');
    }
}