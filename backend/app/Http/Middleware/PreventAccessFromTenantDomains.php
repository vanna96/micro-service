<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class PreventAccessFromTenantDomains
{
    /**
     * Handle an incoming request.
     *
     * Prevents tenant and wildcard subdomains from accessing backend panel routes.
     * Only central domains (e.g. localhost, 127.0.0.1) are permitted.
     */
    public function handle(Request $request, Closure $next)
    {
        $host = strtolower($request->getHost());

        $centralDomains = array_map('strtolower', (array) config('tenancy.central_domains', ['localhost', '127.0.0.1']));
        $appHost = strtolower((string) parse_url((string) config('app.url', ''), PHP_URL_HOST));

        if ($appHost !== '' && ! in_array($appHost, $centralDomains, true)) {
            $centralDomains[] = $appHost;
        }

        if (! in_array($host, $centralDomains, true)) {
            abort(404, 'The requested administration panel is only accessible on the central domain.');
        }

        return $next($request);
    }
}
