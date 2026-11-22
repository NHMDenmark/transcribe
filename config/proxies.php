<?php

/*
 * Which reverse proxies the application trusts (and so believes about the address, scheme and port the
 * visitor used). Read through the configuration, not with env() inside the middleware: with a cached
 * configuration (php artisan config:cache) env() returns nothing outside the config files, and the proxy
 * would silently stop being trusted - the login then redirects to the internal address.
 *
 *   TRUST_PROXIES=*                       every proxy (behind a reverse proxy, or an SSH tunnel)
 *   TRUST_PROXIES=192.168.1.1,10.0.0.1    only these addresses
 *   (unset)                               direct deployment, no proxy
 */
return [
    'trusted' => env('TRUST_PROXIES'),
];
