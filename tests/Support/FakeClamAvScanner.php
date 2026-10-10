<?php

namespace Tests\Support;

use App\Console\Commands\HelpdeskUploadScanCheck;
use App\Services\Security\ClamAvScanner;
use App\Services\Security\ScanResult;

/**
 * Stands in for the real scanner in tests, without any socket. Anything that contains the harmless
 * sentinel below (or the standard EICAR test file) counts as infected. Give it a problem to make every
 * scan and ping fail instead, as when ClamAV is not running.
 */
class FakeClamAvScanner extends ClamAvScanner
{
    public const SENTINEL = 'HELPDESK-TEST-VIRUS-SIGNATURE';

    public int $scans = 0;

    public int $pings = 0;

    /** @var list<string> The contents of every file or stream handed in for scanning, in order. */
    public array $scanned = [];

    public function __construct(private readonly ?string $problem = null)
    {
        parent::__construct();
    }

    public function scan(string $path): ScanResult
    {
        return $this->check((string) file_get_contents($path));
    }

    public function scanStream($source): ScanResult
    {
        return $this->check((string) stream_get_contents($source));
    }

    public function ping(float $timeout = 2.0): ?string
    {
        $this->pings++;

        return $this->problem;
    }

    private function check(string $contents): ScanResult
    {
        $this->scans++;
        $this->scanned[] = $contents;

        if ($this->problem !== null) {
            return ScanResult::error($this->problem);
        }

        return str_contains($contents, self::SENTINEL) || str_contains($contents, HelpdeskUploadScanCheck::eicarTestFile())
            ? ScanResult::infected(self::SENTINEL)
            : ScanResult::clean();
    }
}
