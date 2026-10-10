<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Symfony\Component\HttpFoundation\Response;

/**
 * The `throttle` middleware with one bucket per limit. Laravel counts every unnamed `throttle:N,M` of one
 * signed-in person in the same bucket (the key is only their id), so every polled or busy route used up the
 * small allowance of a rare one: with a page open, a client could be told to "slow down" when filing a ticket
 * (limit 10 a minute). Here `throttle:10,1` and `throttle:120,1` count separately; two routes that declare the
 * same limit still share one bucket. A named limiter (`throttle:api`) is left alone.
 */
class ThrottlePerLimit extends ThrottleRequests
{
    public function handle($request, Closure $next, $maxAttempts = 60, $decayMinutes = 1, $prefix = ''): Response
    {
        if ($prefix === '' && is_numeric($maxAttempts)) {
            $prefix = "limit-{$maxAttempts}-per-{$decayMinutes}:";
        }

        return parent::handle($request, $next, $maxAttempts, $decayMinutes, $prefix);
    }
}
