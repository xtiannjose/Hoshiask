<?php

namespace App\Events;

/**
 * Sent while the production status check (`hoshiask:ops-status`, the detailed /health) collects its advisories.
 * A feature adds its own plain-English note from a listener in app/Listeners, so the status service does not need
 * to know about every feature. Advisories never count as warnings: a warning fails the deploy's health check.
 */
final class ProductionAdvisoriesRequested
{
    /** @var list<string> */
    private array $advisories = [];

    public function add(string $advisory): void
    {
        $this->advisories[] = $advisory;
    }

    /**
     * @return list<string>
     */
    public function advisories(): array
    {
        return $this->advisories;
    }
}
