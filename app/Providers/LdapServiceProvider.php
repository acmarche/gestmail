<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use LdapRecord\Connection;
use LdapRecord\Container;

final class LdapServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Container::addConnection(new Connection([
            'hosts' => [config('ldap.hosts')],
            'base_dn' => config('ldap.base_dn'),
            'username' => config('ldap.username'),
            'password' => config('ldap.password'),
            'port' => (int) config('ldap.port', 389),
            'use_ssl' => (bool) config('ldap.ssl', false),
            'use_tls' => (bool) config('ldap.tls', false),
            'use_sasl' => (bool) config('ldap.sasl', false),
            'timeout' => (int) config('ldap.timeout', 5),
        ]));
    }
}
