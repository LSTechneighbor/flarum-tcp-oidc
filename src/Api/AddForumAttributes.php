<?php

/*
 * This file is part of lstechneighbor/flarum-tcp-oidc.
 *
 * Copyright (c) Larry Squitieri.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace LSTechNeighbor\TCPOIDC\Api;

use Flarum\Api\Context;
use Flarum\Api\Schema;

class AddForumAttributes
{
    public function __invoke(): array
    {
        return [
            // This attribute is used to display the OAuth providers on the login page
            Schema\Str::make('lstechneighbor-tcp-oidc')
                ->visible(fn ($model, Context $context) => $context->getActor()->isGuest())
                ->get(fn () => resolve('lstechneighbor-tcp-oidc.providers.forum')),
        ];
    }
}
