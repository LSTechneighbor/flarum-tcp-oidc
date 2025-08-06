<?php

/*
 * This file is part of fof/oauth.
 *
 * Copyright (c) FriendsOfFlarum.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace LSTechNeighbor\TCPOIDC;

use Flarum\Extend;
use Flarum\Frontend\Document;
use Flarum\User\Event\LoggedOut;
use Flarum\User\Event\RegisteringFromProvider;
use LSTechNeighbor\TCPOIDC\Events\OAuthLoginSuccessful;

return [
    (new Extend\Frontend('forum'))
        ->js(__DIR__.'/js/dist/forum.js')
        ->css(__DIR__.'/resources/less/forum.less')
        ->content(function (Document $document) {
            try {
                $providers = resolve('lstechneighbor-tcp-oidc.providers.forum');
                error_log('TCP OIDC Debug: Forum payload providers: ' . json_encode($providers));
                $document->payload['forum']['lstechneighbor-tcp-oidc'] = $providers;
                $document->payload['lstechneighbor-tcp-oidc'] = $providers;
            } catch (\Exception $e) {
                error_log('TCP OIDC Debug: Error resolving forum providers: ' . $e->getMessage());
                $document->payload['forum']['lstechneighbor-tcp-oidc'] = [];
                $document->payload['lstechneighbor-tcp-oidc'] = [];
            }
        }),

    (new Extend\Frontend('admin'))
        ->js(__DIR__.'/js/dist/admin.js')
        ->css(__DIR__.'/resources/less/admin.less')
        ->content(function (Document $document) {
            $document->payload['lstechneighbor-tcp-oidc'] = resolve('lstechneighbor-tcp-oidc.providers.admin');
        }),

    new Extend\Locales(__DIR__.'/resources/locale'),

    (new Extend\Middleware('forum'))
        ->add(Middleware\ErrorHandler::class)
        ->add(Middleware\BindRequest::class),

    (new Extend\Middleware('api'))
        ->add(Middleware\BindRequest::class),

    (new Extend\Routes('forum'))
        ->get('/auth/linkedin', 'auth.linkedin', Controllers\AuthController::class),

    (new Extend\ServiceProvider())
        ->register(OAuthServiceProvider::class),

    // Removed API resource extensions as they're not needed for our TCP OIDC extension

    (new Extend\Settings())
        ->default('lstechneighbor-tcp-oidc.only_icons', false)
        ->default('lstechneighbor-tcp-oidc.update_email_from_provider', true)
        ->serializeToForum('lstechneighbor-tcp-oidc.only_icons', 'lstechneighbor-tcp-oidc.only_icons', 'boolVal')
        ->default('lstechneighbor-tcp-oidc.popupWidth', 580)
        ->default('lstechneighbor-tcp-oidc.popupHeight', 400)
        ->default('lstechneighbor-tcp-oidc.fullscreenPopup', true)
        ->serializeToForum('lstechneighbor-tcp-oidc.popupWidth', 'lstechneighbor-tcp-oidc.popupWidth', 'intval')
        ->serializeToForum('lstechneighbor-tcp-oidc.popupHeight', 'lstechneighbor-tcp-oidc.popupHeight', 'intval')
        ->serializeToForum('lstechneighbor-tcp-oidc.fullscreenPopup', 'lstechneighbor-tcp-oidc.fullscreenPopup', 'boolVal')
        ->default('lstechneighbor-tcp-oidc.log-oauth-errors', false)
        ->default('lstechneighbor-tcp-oidc.linkedin', true),

    (new Extend\Event())
        ->listen(RegisteringFromProvider::class, Listeners\AssignGroupToUser::class)
        ->listen(OAuthLoginSuccessful::class, Listeners\UpdateEmailFromProvider::class)
        ->listen(LoggedOut::class, Listeners\HandleLogout::class)
        ->subscribe(Listeners\ClearOAuthCache::class),

    // Removed conditional and search extensions as they're not needed for our TCP OIDC extension
];
