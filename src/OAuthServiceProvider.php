<?php

/*
 * This file is part of lstechneighbor/flarum-tcp-oidc.
 *
 * Copyright (c) Larry Squitieri.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace LSTechNeighbor\TCPOIDC;

use Flarum\Foundation\AbstractServiceProvider;
use Flarum\Foundation\Config;
use Flarum\Http\RouteCollection;
use Flarum\Http\RouteHandlerFactory;
use Illuminate\Contracts\Cache\Store as Cache;
use Illuminate\Contracts\Container\Container;

class OAuthServiceProvider extends AbstractServiceProvider
{
    public function register()
    {
        $this->container->tag([
            Providers\LinkedIn::class,
        ], 'lstechneighbor-tcp-oidc.providers');

        // Add OAuth provider routes
        $this->container->resolving('flarum.forum.routes', function (RouteCollection $collection, Container $container) {
            /** @var RouteHandlerFactory $factory */
            $factory = $container->make(RouteHandlerFactory::class);

            $collection->addRoute('GET', new OAuth2RoutePattern(), 'lstechneighbor-tcp-oidc', $factory->toController(Controllers\AuthController::class));
        });
    }

    public function boot()
    {
        /** @var Cache $cache */
        $cache = $this->container->make(Cache::class);
        /** @var Config $config */
        $config = $this->container->make(Config::class);

        $this->container->singleton('lstechneighbor-tcp-oidc.providers.forum', function () use ($cache, $config) {
            // If we're in debug mode, don't cache the providers, but directly return them.
            if ($config->inDebugMode()) {
                return $this->mapProviders();
            }

            $cacheKey = 'lstechneighbor-tcp-oidc.providers.forum';

            $data = $cache->get($cacheKey);
            if ($data === null) {
                $data = $this->mapProviders();
                $cache->forever($cacheKey, $data);
            }

            return $data;
        });

        $this->container->singleton('lstechneighbor-tcp-oidc.providers.admin', function () use ($cache, $config) {
            // If we're in debug mode, don't cache the providers, but directly return them.
            if ($config->inDebugMode()) {
                return $this->mapProviders(true);
            }

            $cacheKey = 'lstechneighbor-tcp-oidc.providers.admin';

            $data = $cache->get($cacheKey);
            if ($data === null) {
                $data = $this->mapProviders(true);
                $cache->forever($cacheKey, $data);
            }

            return $data;
        });
    }

    protected function mapProviders(bool $admin = false): array
    {
        $providers = $this->container->tagged('lstechneighbor-tcp-oidc.providers');

        if ($admin) {
            return array_map(function (Provider $provider) {
                return [
                    'name'   => $provider->name(),
                    'icon'   => $provider->icon(),
                    'link'   => $provider->link(),
                    'fields' => $provider->fields(),
                ];
            }, iterator_to_array($providers));
        }

        $result = array_map(function (Provider $provider) {
            if (!$provider->enabled()) {
                return null;
            }

            return [
                'name'     => $provider->name(),
                'icon'     => $provider->icon(),
                'priority' => $provider->priority(),
            ];
        }, iterator_to_array($providers));

        // Debug logging
        error_log('TCP OIDC Debug - Providers: ' . json_encode($result));
        error_log('TCP OIDC Debug - Admin mode: ' . ($admin ? 'true' : 'false'));

        return $result;
    }
} 