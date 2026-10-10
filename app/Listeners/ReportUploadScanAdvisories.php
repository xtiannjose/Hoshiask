<?php

namespace App\Listeners;

use App\Events\ProductionAdvisoriesRequested;
use App\Services\Security\ClamAvScanner;
use Illuminate\Support\Facades\Cache;

/**
 * Plain-English notes about virus scanning of uploads for the production status check. They never count as
 * warnings.
 */
class ReportUploadScanAdvisories
{
    /** The scanner is asked at most once a minute, so a busy /health cannot hammer it. */
    private const PING_CACHE_SECONDS = 60;

    public function __construct(private readonly ClamAvScanner $scanner) {}

    public function handle(ProductionAdvisoriesRequested $request): void
    {
        if (config('security.upload_scan.driver') !== 'clamav') {
            $request->add("Uploaded files are not scanned for viruses. See 'Upload scanning' in docs/security.md to switch it on.");

            return;
        }

        $problem = Cache::remember('helpdesk:upload-scan:ping', self::PING_CACHE_SECONDS, fn () => $this->scanner->ping(2.0) ?? '');

        if ($problem !== '') {
            $uploads = config('security.upload_scan.on_error') === 'block' ? 'blocked' : 'allowed without a check';
            $request->add("Virus scanning is switched on but the scanner cannot be reached ({$problem}); uploads are {$uploads} meanwhile. Run `php artisan helpdesk:upload-scan-check`.");
        }
    }
}
