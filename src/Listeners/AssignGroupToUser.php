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

        // Minimal debug logging to avoid header size issues
        error_log("TCP OIDC: Processing user registration for provider: " . $provider);
        
        // Set display name from the payload if available
        if ($registration && method_exists($registration, 'getPayload')) {
            $payload = $registration->getPayload();
            if (is_array($payload)) {
                // Try multiple fields for display name (nickname, preferred_username, given_name, name)
                $displayName = $payload['nickname'] ?? $payload['preferred_username'] ?? $payload['given_name'] ?? $payload['name'] ?? null;
                
                if (!empty($displayName)) {
                    $user->display_name = $displayName;
                    error_log("TCP OIDC: Display name set to: " . $displayName);
                }
            }
        }
    }
}
