<?php

namespace App\Services\TicketImport;

use Illuminate\Support\Carbon;
use RuntimeException;
use SplTempFileObject;
use Traversable;

class PreparedImportRowSpool
{
    private SplTempFileObject $buffer;

    public function __construct(int $maxMemoryBytes = 2097152)
    {
        $this->buffer = new SplTempFileObject($maxMemoryBytes);
    }

    /**
     * @param  array<string, mixed>  $preparedRow
     */
    public function append(array $preparedRow): void
    {
        $payload = base64_encode(serialize($preparedRow));
        $written = $this->buffer->fwrite($payload.PHP_EOL);

        if ($written === false) {
            throw new RuntimeException('Unable to write prepared import row to the temporary spool.');
        }
    }

    /**
     * @return Traversable<int, array<string, mixed>>
     */
    public function rows(): Traversable
    {
        $this->buffer->rewind();

        while (! $this->buffer->eof()) {
            $line = $this->buffer->fgets();
            if (! is_string($line)) {
                continue;
            }

            $payload = rtrim($line, "\r\n");
            if ($payload === '') {
                continue;
            }

            $decoded = base64_decode($payload, true);
            if ($decoded === false) {
                throw new RuntimeException('Unable to decode a prepared import row from the temporary spool.');
            }

            $preparedRow = unserialize($decoded, ['allowed_classes' => [Carbon::class]]);
            if (! is_array($preparedRow)) {
                throw new RuntimeException('Prepared import spool contained an unexpected row payload.');
            }

            yield $preparedRow;
        }
    }
}
