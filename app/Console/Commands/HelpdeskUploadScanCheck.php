<?php

namespace App\Console\Commands;

use App\Services\Security\ClamAvScanner;
use App\Services\Security\ScanResult;
use Illuminate\Console\Command;
use RuntimeException;

class HelpdeskUploadScanCheck extends Command
{
    protected $signature = 'helpdesk:upload-scan-check';

    protected $description = 'Check that uploaded files are really scanned for viruses, by sending the standard EICAR test file to the scanner.';

    public function handle(ClamAvScanner $scanner): int
    {
        $driver = (string) config('security.upload_scan.driver');
        $this->line('Upload scanning driver: '.$driver);

        if ($driver === 'off') {
            $this->info('Virus scanning is switched off (HELPDESK_UPLOAD_SCAN=off), so there is nothing to check.');

            return self::SUCCESS;
        }

        if ($driver !== 'clamav') {
            $this->error('HELPDESK_UPLOAD_SCAN is "'.$driver.'", which is not recognised. Use "clamav" to switch scanning on or "off" to leave it off.');

            return self::FAILURE;
        }

        $problem = $scanner->ping();
        $this->line('Scanner reachable: '.($problem === null ? 'yes' : 'no'));

        if ($problem !== null) {
            $this->line('EICAR test file detected: not tested');

            return $this->notWorking($problem);
        }

        $result = $this->scanTestFile($scanner);

        if ($result->isInfected()) {
            $this->line('EICAR test file detected: yes ('.$result->signature.')');
            $this->info('Virus scanning works.');

            return self::SUCCESS;
        }

        $this->line('EICAR test file detected: no');

        return $this->notWorking($result->isError()
            ? (string) $result->errorMessage
            : 'the scanner called the standard test file clean. Its virus signatures may still be downloading or missing (check that the clamav-freshclam service is running).');
    }

    /**
     * The standard harmless antivirus test file, put together in memory. It is never written to disk
     * and never appears in one piece in the source, because antivirus software on a developer's
     * computer would flag it.
     */
    public static function eicarTestFile(): string
    {
        return 'X5O!P%@AP[4'.chr(92).'PZX54(P^)7CC)7}'.'$EICAR-STANDARD-ANTIVIRUS-TEST-FILE!'.'$H+H*';
    }

    private function scanTestFile(ClamAvScanner $scanner): ScanResult
    {
        $memory = fopen('php://memory', 'w+b') ?: throw new RuntimeException('Could not open a memory stream.');
        fwrite($memory, self::eicarTestFile());
        rewind($memory);

        try {
            return $scanner->scanStream($memory);
        } finally {
            fclose($memory);
        }
    }

    private function notWorking(string $reason): int
    {
        $this->error('Virus scanning is NOT working: '.$reason);

        return self::FAILURE;
    }
}
