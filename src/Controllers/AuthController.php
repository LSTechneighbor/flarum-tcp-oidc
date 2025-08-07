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
        error_log("🚀🚀🚀 TCP OIDC CODE IS RUNNING! 🚀🚀🚀");
        error_log("TCP OIDC: AuthController handle method called!");
        error_log("TCP OIDC: Starting OAuth flow...");
        
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
        error_log("🚀🚀🚀 ENTERING handleCallback! 🚀🚀🚀");
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

            error_log("TCP OIDC: User data received successfully");
        
            // ===== COMPREHENSIVE USER DATA DEBUG LOGGING =====
            error_log("TCP OIDC: ===== RAW USER DATA FROM OIDC PROVIDER =====");
            
            // Log the complete user data array
            $userData = $user->toArray();
            
            // Write complete user data to a more accessible location
            $debugFile = '/var/log/tcp_oidc_debug.json';
            file_put_contents($debugFile, json_encode($userData, JSON_PRETTY_PRINT));
            error_log("TCP OIDC: Complete user data written to: " . $debugFile);
            
            // Log each field individually to avoid truncation
            error_log("TCP OIDC: === USER DATA FIELDS ===");
            foreach ($userData as $key => $value) {
                if (is_array($value) || is_object($value)) {
                    error_log("TCP OIDC: {$key}: " . json_encode($value));
                } else {
                    error_log("TCP OIDC: {$key}: " . $value);
                }
            }
            error_log("TCP OIDC: === END USER DATA FIELDS ===");
            
            // Log individual fields that might be relevant
            $fieldsToCheck = [
                'sub', 'id', 'email', 'email_address', 'mail', 'name', 'given_name', 
                'family_name', 'nickname', 'preferred_username', 'username', 'picture', 
                'avatar', 'org', 'organization', 'org_name', 'groups', 'roles', 
                'profile', 'website', 'locale', 'zoneinfo', 'updated_at'
            ];
            
            foreach ($fieldsToCheck as $field) {
                if (isset($userData[$field])) {
                    error_log("TCP OIDC: Field '{$field}': " . json_encode($userData[$field]));
                }
            }
            
            // Log user object methods if available
            if (method_exists($user, 'getId')) {
                error_log("TCP OIDC: User ID (getId): " . $user->getId());
            }
            if (method_exists($user, 'getEmail')) {
                error_log("TCP OIDC: User Email (getEmail): " . $user->getEmail());
            }
            if (method_exists($user, 'getName')) {
                error_log("TCP OIDC: User Name (getName): " . $user->getName());
            }
            if (method_exists($user, 'getNickname')) {
                error_log("TCP OIDC: User Nickname (getNickname): " . $user->getNickname());
            }
            
            // Log token information
            error_log("TCP OIDC: Token class: " . get_class($token));
            if (method_exists($token, 'getValues')) {
                error_log("TCP OIDC: Token values: " . json_encode($token->getValues()));
            }
            
            error_log("TCP OIDC: ===== END RAW USER DATA DEBUG =====");
            // ===== END COMPREHENSIVE DEBUG LOGGING =====
        
            // Get user data array to access email and name
            $email = $userData['email'] ?? $userData['email_address'] ?? $userData['mail'] ?? null;
            $name = $userData['name'] ?? $userData['given_name'] ?? $userData['nickname'] ?? null;
            
            error_log("TCP OIDC: Extracted email: " . ($email ?? 'null'));
            error_log("TCP OIDC: Extracted name: " . ($name ?? 'null'));
            error_log("TCP OIDC: About to create Flarum response");
            
            try {
                // Get parameters for response->make
                $providerName = $provider->name();
                
                // Get user ID from user data array (OpenID Connect uses 'sub' field)
                $userData = $user->toArray();
                $userId = $userData['sub'] ?? $userData['id'] ?? $user->getId();
                
                error_log("TCP OIDC: Calling response->make with provider: '$providerName', userId: '$userId'");
                
                $response = $this->response->make(
                    $providerName,
                    $userId,
                    function (Registration $registration) use ($user, $provider) {
                        $this->setSuggestions($registration, $user, $provider);
                    }
                );
                
                error_log("TCP OIDC: Flarum response created successfully");
                return $response;
            } catch (\Exception $e) {
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
        
        // Get user data array to access email and name
        $userData = $user->toArray();
        
        // Get email from user data
        $email = $userData['email'] ?? $userData['email_address'] ?? $userData['mail'] ?? null;

        if (empty($email)) {
            error_log("TCP OIDC: No email found in user data");
            throw new \Exception('No email address provided by TCP OIDC provider');
        }

        error_log("TCP OIDC: Using email: " . $email);

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

        error_log("TCP OIDC: Using username: " . $username);
        error_log("TCP OIDC: Using org name: " . ($orgName ?? 'null'));

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
            
            // Note: Flarum doesn't support setting nicknames during registration
            // The nickname will need to be set manually or through a separate process
            
            // Only provide avatar if it's a valid URL
            if (!empty($avatar) && filter_var($avatar, FILTER_VALIDATE_URL)) {
                $registration->provideAvatar($avatar);
            }
                
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