<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies as Middleware;
use Illuminate\Http\Request;

class TrustProxies extends Middleware
{
    /**
     * The trusted proxies for this application.
     *
     * Set TRUST_PROXIES=* in .env to trust all proxies (e.g. behind a reverse proxy or SSH tunnel).
     * Set TRUST_PROXIES=192.168.1.1,10.0.0.1 for specific IPs.
     * Leave unset (null) for direct deployments with no proxy.
     *
     * @var array<int, string>|string|null
     */
    protected $proxies;

    /**
     * The headers used to detect proxies.
     * Includes X-Forwarded-Prefix for deployments served under a sub-path.
     *
     * @var int
     */
    protected $headers =
        Request::HEADER_X_FORWARDED_FOR |
        Request::HEADER_X_FORWARDED_HOST |
        Request::HEADER_X_FORWARDED_PORT |
        Request::HEADER_X_FORWARDED_PROTO |
        Request::HEADER_X_FORWARDED_AWS_ELB |
        Request::HEADER_X_FORWARDED_PREFIX;

    public function __construct()
    {
        // through the configuration (config/proxies.php): env() does not work here once the configuration is cached
        $trust = config('proxies.trusted');
        if ($trust === '*') {
            $this->proxies = '*';
        } elseif ($trust) {
            $this->proxies = array_map('trim', explode(',', $trust));
        }
        // null = no proxy trust (default for direct deployments)
    }
}
