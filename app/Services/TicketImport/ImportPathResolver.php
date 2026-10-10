<?php

namespace App\Services\TicketImport;

use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use RuntimeException;

class ImportPathResolver
{
    public function resolve(string $path): string
    {
        $trimmedPath = trim($path);
        if ($trimmedPath === '') {
            throw new InvalidArgumentException('Import path cannot be empty.');
        }

        $storageCandidate = Storage::disk((string) config('helpdesk.ticket_import_disk', 'local'))
            ->path(trim((string) config('helpdesk.ticket_import_path', 'imports').'/'.$trimmedPath, '/'));

        $candidates = [
            $trimmedPath,
            base_path($trimmedPath),
            $storageCandidate,
        ];

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && is_file($candidate)) {
                return $candidate;
            }
        }

        throw new RuntimeException('Import file not found: '.$trimmedPath);
    }
}
