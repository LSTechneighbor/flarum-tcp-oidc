<?php

/*
 * This file is part of fof/oauth.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace LSTechNeighbor\TCPOIDC\Listeners;

use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\Event\RegisteringFromProvider;
use Flarum\User\User;

class AssignGroupToUser
{
    /**
     * @var SettingsRepositoryInterface
     */
    protected $settings;

    /**
     * @param SettingsRepositoryInterface $settings
     */
    public function __construct(SettingsRepositoryInterface $settings)
    {
        $this->settings = $settings;
    }

    /**
     * @param RegisteringFromProvider $event
     */
    public function handle(RegisteringFromProvider $event)
    {
        $provider = $event->provider;
        $user = $event->user;
        $registration = $event->registration;

        // Get the group ID for this provider
        $groupId = $this->settings->get("lstechneighbor-tcp-oidc.{$provider}.group");

        // If a group is specified, assign it to the user
        if ($groupId && is_numeric($groupId)) {
            $user->afterSave(function (User $user) use ($groupId) {
                // Attach the group to the user
                $user->groups()->attach($groupId);
            });
        }

        // Debug: Log all available data to help troubleshoot nickname issue
        error_log("TCP OIDC Debug - Provider: " . $provider);
        error_log("TCP OIDC Debug - User data: " . json_encode($user->toArray()));
        
        if ($registration) {
            error_log("TCP OIDC Debug - Registration object exists");
            
            // Log registration methods
            $registrationMethods = get_class_methods($registration);
            error_log("TCP OIDC Debug - Registration methods: " . json_encode($registrationMethods));
            
            // Log payload if available
            if (method_exists($registration, 'getPayload')) {
                $payload = $registration->getPayload();
                error_log("TCP OIDC Debug - Payload: " . json_encode($payload));
            }
            
            // Log provided data if available
            if (method_exists($registration, 'getProvided')) {
                $provided = $registration->getProvided();
                error_log("TCP OIDC Debug - Provided: " . json_encode($provided));
            }
            
            // Log suggested data if available
            if (method_exists($registration, 'getSuggested')) {
                $suggested = $registration->getSuggested();
                error_log("TCP OIDC Debug - Suggested: " . json_encode($suggested));
            }
        } else {
            error_log("TCP OIDC Debug - No registration object available");
        }
        
        // Log event properties
        if (property_exists($event, 'resourceOwner')) {
            error_log("TCP OIDC Debug - Resource Owner: " . json_encode($event->resourceOwner));
        }
        
        if (property_exists($event, 'token')) {
            error_log("TCP OIDC Debug - Token: " . json_encode($event->token));
        }
        
        // Set nickname from the payload if available
        if ($registration && method_exists($registration, 'getPayload')) {
            $payload = $registration->getPayload();
            if (is_array($payload) && isset($payload['nickname']) && !empty($payload['nickname'])) {
                $user->nickname = $payload['nickname'];
                error_log("TCP OIDC Debug - Nickname set to: " . $payload['nickname']);
            } else {
                error_log("TCP OIDC Debug - Nickname not found or empty in payload");
            }
        }
    }
}
