<?php

/*
 * This file is part of lstechneighbor/flarum-tcp-oidc.
 *
 * Copyright (c) LSTechNeighbor.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace LSTechNeighbor\TCPOIDC\Listeners;

use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\Event\RegisteringFromProvider;
use Flarum\User\User;
use Illuminate\Support\Arr;

/**
 * Listener to handle user registration from OIDC provider.
 * 
 * This listener:
 * - Assigns users to configured groups based on their provider
 * - Sets user nickname from OIDC payload data
 */
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
     * Handle the RegisteringFromProvider event.
     *
     * @param RegisteringFromProvider $event
     */
    public function handle(RegisteringFromProvider $event)
    {
        $user = $this->extractUser($event);
        $payload = $this->extractPayload($event);
        
        if (!$user || !$payload) {
            return;
        }

        $this->assignUserToGroup($user, $event->provider);
        $this->setUserNickname($user, $payload);
    }

    /**
     * Extract user object from the event.
     *
     * @param RegisteringFromProvider $event
     * @return User|null
     */
    private function extractUser(RegisteringFromProvider $event): ?User
    {
        return $event->user ?? null;
    }

    /**
     * Extract payload data from the event.
     *
     * @param RegisteringFromProvider $event
     * @return array|null
     */
    private function extractPayload(RegisteringFromProvider $event): ?array
    {
        $payload = $event->payload ?? $event->data ?? $event->suggestions ?? null;
        
        if (!$payload) {
            return null;
        }
        
        // Convert object to array for consistent handling
        if (is_object($payload)) {
            $payload = (array) $payload;
        }
        
        return is_array($payload) ? $payload : null;
    }

    /**
     * Assign user to the configured group for the provider.
     *
     * @param User $user
     * @param string $provider
     */
    private function assignUserToGroup(User $user, string $provider): void
    {
        $groupId = $this->settings->get("lstechneighbor-tcp-oidc.{$provider}.group");

        if ($groupId && is_numeric($groupId)) {
            $user->afterSave(function (User $user) use ($groupId) {
                $user->groups()->syncWithoutDetaching([$groupId]);
            });
        }
    }

    /**
     * Set user nickname from OIDC payload.
     *
     * @param User $user
     * @param array $payload
     */
    private function setUserNickname(User $user, array $payload): void
    {
        // Priority order for nickname fields
        $nicknameFields = ['nickname', 'preferred_username', 'given_name', 'name'];
        
        $displayName = Arr::first($nicknameFields, function ($field) use ($payload) {
            return !empty($payload[$field]);
        });
        
        if ($displayName && !empty($payload[$displayName])) {
            $user->nickname = $payload[$displayName];
            
            // Ensure nickname is persisted
            $user->afterSave(function (User $user) use ($payload, $displayName) {
                if (empty($user->nickname)) {
                    $user->nickname = $payload[$displayName];
                    $user->save();
                }
            });
        }
    }
}
