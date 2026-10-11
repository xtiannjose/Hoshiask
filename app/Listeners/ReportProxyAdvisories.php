<?php

namespace App\Listeners;

use App\Events\ProductionAdvisoriesRequested;

/**
 * A plain-English note when TRUSTED_PROXIES trusts every sender. It never counts as a warning: behind some cloud
 * load balancers "*" is the only practical value.
 */
class ReportProxyAdvisories
{
    public function handle(ProductionAdvisoriesRequested $request): void
    {
        if (trim((string) config('security.trusted_proxies')) !== '*') {
            return;
        }

        $request->add('TRUSTED_PROXIES is "*": any sender may claim to be a proxy, so anyone who can reach the server directly can fake the visitor address that the sign-in limit and System Logs use. Fine only when the server cannot be reached except through your proxy or load balancer; otherwise list the proxy addresses.');
    }
}
