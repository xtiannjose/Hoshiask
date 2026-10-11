<?php

namespace App\Listeners;

use App\Events\ProductionAdvisoriesRequested;

/**
 * A plain-English note when the privacy notice and the terms still show the example contact addresses from
 * .env.example. It never counts as a warning.
 */
class ReportLegalPlaceholderAdvisories
{
    public function handle(ProductionAdvisoriesRequested $request): void
    {
        $placeholders = [];

        foreach (['LEGAL_DPO_EMAIL' => 'legal.dpo_email', 'LEGAL_SUPPORT_EMAIL' => 'legal.support_email'] as $setting => $key) {
            if (preg_match('/@example\.(com|org|net)$/i', trim((string) config($key))) === 1) {
                $placeholders[] = $setting;
            }
        }

        if ($placeholders === []) {
            return;
        }

        $request->add(implode(' and ', $placeholders).' still hold'.(count($placeholders) === 1 ? 's' : '').' the example address from .env.example, so the privacy notice and the terms send people to a mailbox that does not exist. Set a real address.');
    }
}
