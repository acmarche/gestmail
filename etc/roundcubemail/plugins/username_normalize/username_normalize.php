<?php

namespace plugins\username_normalize;

use rcube_plugin;

class username_normalize extends rcube_plugin
{
    public $task = 'login';

    // Domaines à supprimer si tapés au login.
    // Conserve la forme "nue" (sans @) comme identifiant DB canonique.
    private $strip_domains = ['marche.be', 'citoyen.marche.be'];

    function init()
    {
        $this->add_hook('authenticate', [$this, 'normalize']);
    }

    function normalize($args)
    {
        if (!empty($args['user']) && strpos($args['user'], '@') !== false) {
            list($local, $domain) = explode('@', $args['user'], 2);
            if (in_array(strtolower($domain), $this->strip_domains, true)) {
                $args['user'] = strtolower($local);
            }
        }

        return $args;
    }
}
