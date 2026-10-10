<?php

namespace App\Services\Security;

use Closure;
use RuntimeException;
use Throwable;

/**
 * Talks to a ClamAV daemon (clamd) with its INSTREAM command. The file is sent in 64 KiB pieces, so
 * memory use stays flat whatever the file size. Neither public method throws: a scanner that is down,
 * slow or confused comes back as an error, and the caller decides what that means for the upload.
 */
class ClamAvScanner
{
    private const CHUNK_BYTES = 65536;

    /** Real replies are a few dozen bytes; anything longer is not clamd talking. */
    private const MAX_REPLY_BYTES = 4096;

    /**
     * @param  (Closure(float): resource)|null  $connect  Opens a stream to the scanner, waiting at most the given
     *                                                    number of seconds. Left empty, the scanner named in
     *                                                    config/security.php is used; tests pass one end of a socket pair.
     */
    public function __construct(private readonly ?Closure $connect = null) {}

    public function scan(string $path): ScanResult
    {
        $file = is_file($path) ? @fopen($path, 'rb') : false;

        if ($file === false) {
            return ScanResult::error('The file could not be read.');
        }

        try {
            return $this->scanStream($file);
        } finally {
            fclose($file);
        }
    }

    /**
     * @param  resource  $source  Read from where it is now to its end.
     */
    public function scanStream($source): ScanResult
    {
        $socket = null;

        try {
            $socket = $this->open($this->timeout());
            $this->send($socket, "zINSTREAM\0");

            while (($chunk = fread($source, self::CHUNK_BYTES)) !== '') {
                if ($chunk === false) {
                    throw new RuntimeException('The file could not be read.');
                }

                $this->send($socket, pack('N', strlen($chunk)).$chunk);
            }

            $this->send($socket, pack('N', 0));

            return $this->interpret($this->readReply($socket));
        } catch (Throwable $exception) {
            return ScanResult::error($exception->getMessage());
        } finally {
            if (is_resource($socket)) {
                fclose($socket);
            }
        }
    }

    /**
     * Asks the scanner whether it is there. Returns null when it answers, otherwise the reason it did not.
     */
    public function ping(float $timeout = 2.0): ?string
    {
        $socket = null;

        try {
            $socket = $this->open($timeout);
            $this->send($socket, "zPING\0");
            $reply = $this->readReply($socket);

            return $reply === 'PONG' ? null : 'The scanner answered "'.$reply.'" instead of PONG.';
        } catch (Throwable $exception) {
            return $exception->getMessage();
        } finally {
            if (is_resource($socket)) {
                fclose($socket);
            }
        }
    }

    private function interpret(string $reply): ScanResult
    {
        if ($reply === 'stream: OK') {
            return ScanResult::clean();
        }

        if (preg_match('/^stream: (.+) FOUND$/', $reply, $found) === 1) {
            return ScanResult::infected($found[1]);
        }

        return ScanResult::error('The scanner gave an unexpected answer: "'.$reply.'".');
    }

    /**
     * @return resource
     */
    private function open(float $timeout)
    {
        $socket = $this->connect !== null
            ? ($this->connect)($timeout)
            : $this->connectToConfiguredScanner($timeout);

        // Reading and writing give up after the same time as the connection attempt.
        stream_set_timeout($socket, (int) $timeout, (int) (fmod($timeout, 1) * 1_000_000));

        return $socket;
    }

    /**
     * @return resource
     */
    private function connectToConfiguredScanner(float $timeout)
    {
        $socketPath = trim((string) config('security.upload_scan.clamav.socket'));
        $address = $socketPath !== ''
            ? 'unix://'.$socketPath
            : 'tcp://'.config('security.upload_scan.clamav.host').':'.config('security.upload_scan.clamav.port');

        $socket = @stream_socket_client($address, $errorCode, $errorMessage, $timeout);

        if ($socket === false) {
            throw new RuntimeException('Could not connect to the scanner at '.$address.' ('.($errorMessage ?: 'unknown error').').');
        }

        return $socket;
    }

    /**
     * @param  resource  $socket
     */
    private function send($socket, string $bytes): void
    {
        // A socket may take only part of a large write; keep going until everything is sent.
        while ($bytes !== '') {
            $written = @fwrite($socket, $bytes);

            if ($written === false || $written === 0) {
                throw new RuntimeException('The scanner stopped accepting the file.');
            }

            $bytes = substr($bytes, $written);
        }
    }

    /**
     * Reads up to the NUL byte that ends every reply to a "z" command. A reply that never gets its NUL
     * is treated as no reply at all.
     *
     * @param  resource  $socket
     */
    private function readReply($socket): string
    {
        $reply = '';

        do {
            if (strlen($reply) >= self::MAX_REPLY_BYTES) {
                throw new RuntimeException('The scanner sent a reply that was far too long.');
            }

            $part = fread($socket, 1024);

            if ($part === false || $part === '') {
                throw new RuntimeException(stream_get_meta_data($socket)['timed_out']
                    ? 'The scanner did not reply in time.'
                    : 'The scanner closed the connection before it finished its reply.');
            }

            $reply .= $part;
        } while (($end = strpos($reply, "\0")) === false);

        return substr($reply, 0, $end);
    }

    private function timeout(): float
    {
        $seconds = (float) config('security.upload_scan.timeout');

        return $seconds > 0 ? $seconds : 10.0;
    }
}
