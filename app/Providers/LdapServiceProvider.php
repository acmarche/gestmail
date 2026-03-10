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
        foreach (config('ldap.connections') as $name => $config) {
            Container::addConnection(new Connection([
                'hosts' => [$config['hosts']],
                'base_dn' => $config['base_dn'],
                'username' => $config['username'],
                'password' => $config['password'],
                'port' => (int) $config['port'],
                'use_ssl' => (bool) $config['ssl'],
                'use_tls' => (bool) $config['tls'],
                'use_sasl' => (bool) $config['sasl'],
                'timeout' => (int) $config['timeout'],
            ]), $name);
        }

        Container::setDefaultConnection('citoyen');
    }
}
