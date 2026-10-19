<?php

namespace Overlook\Foundation\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Overlook\Foundation\Analytics\BeaconMarkup;
use Overlook\Foundation\Http\Middleware\Concerns\InjectsMarkup;
use Symfony\Component\HttpFoundation\Response;

/**
 * Puts the studio's first-party analytics beacon on a live site.
 *
 * The inverse of the review widget: production only, because a preview would
 * record nothing but the studio looking at its own work. The provisioner writes
 * the key and the address into a production environment only when the studio
 * has analytics switched on, so most sites never reach the injection at all.
 */
class InjectAnalyticsBeacon
{
    use InjectsMarkup;

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! app()->environment('production')) {
            return $response;
        }

        $key = config('foundation.analytics.key');
        $source = config('foundation.analytics.url');

        if (! config('foundation.analytics.enabled')
            || ! is_string($key) || $key === ''
            || ! is_string($source) || $source === '') {
            return $response;
        }

        return $this->injectBeforeBodyClose($response, BeaconMarkup::for($key, $source));
    }
}
