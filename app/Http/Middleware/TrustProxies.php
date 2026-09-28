<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies as Middleware;
use Illuminate\Http\Request;

class TrustProxies extends Middleware
{
    /**
     * Trust all proxies (Cloudflare, nginx, load balancers).
     * Override via APP_TRUSTED_PROXIES env var if needed.
     *
     * @var array<int, string>|string|null
     */
    protected $proxies = '*';

    /**
     * Headers to inspect for proxy-forwarded data.
     * We include all standard headers so both Cloudflare and
     * nginx-style setups work without configuration.
     *
     * @var int
     */
    protected $headers =
    Request::HEADER_X_FORWARDED_FOR    |
        Request::HEADER_X_FORWARDED_HOST   |
        Request::HEADER_X_FORWARDED_PORT   |
        Request::HEADER_X_FORWARDED_PROTO  |
        Request::HEADER_X_FORWARDED_AWS_ELB;
}
