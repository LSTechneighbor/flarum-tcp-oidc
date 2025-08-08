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
        error_log('TCP OIDC: Processing user registration for provider: ' . $event->provider);
        
        // The RegisteringFromProvider event has different properties
        // Let's check what's available in the event
        $eventProperties = get_object_vars($event);
        error_log('TCP OIDC: Event properties: ' . json_encode(array_keys($eventProperties)));
        
        // Try to access user and payload from different possible properties
        $user = null;
        $payload = null;
        
        if (isset($event->user)) {
            $user = $event->user;
        }
        
        if (isset($event->payload)) {
            $payload = $event->payload;
        } elseif (isset($event->data)) {
            $payload = $event->data;
        } elseif (isset($event->suggestions)) {
            $payload = $event->suggestions;
        }
        
        error_log('TCP OIDC: User object available: ' . ($user ? 'yes' : 'no'));
        error_log('TCP OIDC: Payload available: ' . ($payload ? 'yes' : 'no'));
        
        if (!$user || !$payload) {
            error_log('TCP OIDC: Missing user or payload data');
            return;
        }

        $provider = $event->provider;

        // Get the group ID for this provider
        $groupId = $this->settings->get("lstechneighbor-tcp-oidc.{$provider}.group");

        // If a group is specified, assign it to the user
        if ($groupId && is_numeric($groupId)) {
            $user->afterSave(function (User $user) use ($groupId) {
                // Attach the group to the user
                $user->groups()->attach($groupId);
            });
        }
        
        error_log('TCP OIDC: Payload type: ' . gettype($payload));
        error_log('TCP OIDC: Payload contents: ' . json_encode($payload));
        
        if (!is_array($payload) && !is_object($payload)) {
            error_log('TCP OIDC: Invalid payload type');
            return;
        }
        
        // Convert object to array for easier handling
        if (is_object($payload)) {
            $payload = (array) $payload;
        }
        
        if (is_array($payload)) {
            error_log('TCP OIDC: Available payload fields: ' . implode(', ', array_keys($payload)));
            // Try multiple fields for display name (nickname, preferred_username, given_name, name)
            $displayName = $payload['nickname'] ?? $payload['preferred_username'] ?? $payload['given_name'] ?? $payload['name'] ?? null;
            
            if (!empty($displayName)) {
                $user->display_name = $displayName;
                error_log('TCP OIDC: Display name set to: ' . $displayName);
                
                // Save the user to ensure the display_name is persisted
                $user->afterSave(function (User $user) use ($displayName) {
                    if (empty($user->display_name)) {
                        $user->display_name = $displayName;
                        $user->save();
                        error_log('TCP OIDC: Display name saved in afterSave: ' . $displayName);
                    }
                });
            } else {
                error_log('TCP OIDC: No suitable display name found in payload');
            }
        } else {
            error_log('TCP OIDC: Payload is not an array');
        }
    }
}
