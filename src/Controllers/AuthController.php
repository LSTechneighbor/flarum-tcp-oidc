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
            error_log("TCP OIDC: Error in startAuthFlow: " . $e->getMessage());
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

            error_log("TCP OIDC: Received authorization code: " . $code);
            error_log("TCP OIDC: State parameter: " . $state);

            $redirectUri = $this->getRedirectUri($request, $provider->name());
            error_log("TCP OIDC: Redirect URI: " . $redirectUri);
            
            $oauthProvider = $provider->provider($redirectUri);
            error_log("TCP OIDC: OAuth provider created successfully");

            // Exchange code for token
            error_log("TCP OIDC: Attempting to exchange code for token...");
            $token = $oauthProvider->getAccessToken('authorization_code', [
                'code' => $code
            ]);

            error_log("TCP OIDC: Token received successfully");
            error_log("TCP OIDC: Token type: " . get_class($token));

            // Get user info
            error_log("TCP OIDC: Attempting to get user info...");
            $user = $oauthProvider->getResourceOwner($token);

            error_log("TCP OIDC: User data received: " . print_r($user->toArray(), true));
            error_log("TCP OIDC: User ID: " . $user->getId());
            error_log("TCP OIDC: User email: " . ($user->getEmail() ?? 'null'));
            error_log("TCP OIDC: User name: " . ($user->getName() ?? 'null'));

            // Use Flarum's OAuth response factory to handle the registration/login
            error_log("TCP OIDC: Creating Flarum response with provider: " . $provider->name() . ", user ID: " . $user->getId());
            
            // Set error handler to catch warnings
            set_error_handler(function($severity, $message, $file, $line) {
                error_log("TCP OIDC: PHP Warning: $message in $file on line $line");
                error_log("TCP OIDC: Warning severity: $severity");
                error_log("TCP OIDC: Full warning context: severity=$severity, message='$message', file='$file', line=$line");
                return true; // Don't execute the internal error handler
            });
            
            try {
                // Log the exact parameters being passed to response->make
                $providerName = $provider->name();
                $userId = $user->getId();
                error_log("TCP OIDC: About to call response->make with provider: '$providerName', userId: '$userId'");
                error_log("TCP OIDC: Response factory class: " . get_class($this->response));
                error_log("TCP OIDC: User object class: " . get_class($user));
                error_log("TCP OIDC: Provider object class: " . get_class($provider));
                
                $response = $this->response->make(
                    $providerName,
                    $userId,
                    function (Registration $registration) use ($user, $provider) {
                        error_log("TCP OIDC: Inside registration callback");
                        $this->setSuggestions($registration, $user, $provider);
                        error_log("TCP OIDC: Registration callback completed");
                    }
                );
                
                // Restore error handler
                restore_error_handler();
                
                error_log("TCP OIDC: Flarum response created successfully");
                return $response;
            } catch (\Exception $e) {
                restore_error_handler();
                error_log("TCP OIDC: Exception in response creation: " . $e->getMessage());
                throw $e;
            }
        } catch (\Exception $e) {
            error_log("TCP OIDC: Error in handleCallback: " . $e->getMessage());
            error_log("TCP OIDC: Error class: " . get_class($e));
            error_log("TCP OIDC: Error trace: " . $e->getTraceAsString());
            
            // Redirect to forum with error
            $forumUrl = (string) $request->getUri()->withPath('/');
            $errorUrl = $forumUrl . '?oauth_error=callback&message=' . urlencode($e->getMessage());
            header('Location: ' . $errorUrl);
            exit;
        }
    }

    protected function setSuggestions(Registration $registration, $user, Provider $provider)
    {
        error_log("TCP OIDC: Setting registration suggestions");
        
        // Get email from user data
        $email = $user->getEmail();
        
        if (empty($email)) {
            // Try to get email from user array data
            $userData = $user->toArray();
            error_log("TCP OIDC: User data array: " . print_r($userData, true));
            $email = $userData['email'] ?? $userData['email_address'] ?? $userData['mail'] ?? null;
        }

        if (empty($email)) {
            error_log("TCP OIDC: No email found in user data");
            throw new \Exception('No email address provided by TCP OIDC provider');
        }

        error_log("TCP OIDC: Using email: " . $email);

        // Get username from user data
        $username = $user->getName() ?? $user->getNickname() ?? $user->getUsername() ?? '';
        
        // Get avatar if available
        $avatar = $user->getAvatar() ?? '';

        error_log("TCP OIDC: Using username: " . $username);
        error_log("TCP OIDC: Using avatar: " . $avatar);

        try {
            $registration
                ->provideTrustedEmail($email)
                ->suggestUsername($username)
                ->provideAvatar($avatar)
                ->setPayload($user->toArray());
                
            error_log("TCP OIDC: Registration suggestions set successfully");
        } catch (\Exception $e) {
            error_log("TCP OIDC: Error setting registration suggestions: " . $e->getMessage());
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