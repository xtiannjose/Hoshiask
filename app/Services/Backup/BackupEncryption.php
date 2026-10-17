<?php

namespace App\Services\Backup;

use Generator;
use RuntimeException;

/**
 * Encrypts a backup in 1 MiB pieces with AES-256-GCM, so memory stays flat whatever the size.
 *
 * File format "HBK1" (scripts/hoshiask-backup-decrypt.php reads the same thing without Laravel):
 *
 *   "HBK1\n"
 *   records:  flag (1 byte: 0 = more follow, 1 = last)  length of ciphertext (4 bytes, big-endian)
 *             IV (12 bytes)  GCM tag (16 bytes)  ciphertext
 *
 * Each record is authenticated together with "HBK1", its position (8 bytes, big-endian, counting from 0) and its
 * flag, so a wrong key, a changed byte, records swapped or repeated, a missing end and anything after the end are
 * all detected. Empty input still gets one (empty) last record.
 */
class BackupEncryption
{
    public const MAGIC = "HBK1\n";

    public const CHUNK_BYTES = 1048576;

    private const CIPHER = 'aes-256-gcm';

    private const IV_BYTES = 12;

    private const TAG_BYTES = 16;

    private const RECORD_HEADER_BYTES = 1 + 4 + self::IV_BYTES + self::TAG_BYTES;

    private string $key;

    public function __construct(string $key)
    {
        $this->key = self::parseKey($key);
    }

    public static function generateKey(): string
    {
        return 'base64:'.base64_encode(random_bytes(32));
    }

    /**
     * Turns the "base64:..." setting into the 32 raw key bytes.
     */
    public static function parseKey(string $key): string
    {
        $raw = str_starts_with($key, 'base64:') ? base64_decode(substr($key, 7), true) : false;

        if (! is_string($raw) || strlen($raw) !== 32) {
            throw new RuntimeException('The backup key is not valid. It must look like base64:... and come from `php artisan hoshiask:backup-key`.');
        }

        return $raw;
    }

    /**
     * @param  resource  $input
     * @param  resource  $output
     */
    public function encrypt($input, $output): void
    {
        self::write($output, self::MAGIC);
        $index = 0;
        $chunk = self::readUpTo($input, self::CHUNK_BYTES);

        while (true) {
            $next = $chunk === '' ? '' : self::readUpTo($input, self::CHUNK_BYTES);
            $last = $next === '';
            $iv = random_bytes(self::IV_BYTES);
            $tag = '';
            $ciphertext = openssl_encrypt($chunk, self::CIPHER, $this->key, OPENSSL_RAW_DATA, $iv, $tag, self::aad($index, $last), self::TAG_BYTES);

            if ($ciphertext === false) {
                throw new RuntimeException('Encryption failed.');
            }

            self::write($output, ($last ? "\x01" : "\x00").pack('N', strlen($ciphertext)).$iv.$tag.$ciphertext);

            if ($last) {
                return;
            }

            $chunk = $next;
            $index++;
        }
    }

    /**
     * Yields the plain bytes piece by piece. Throws as soon as anything is wrong; a caller that writes the pieces
     * somewhere must treat what it wrote as unusable when that happens.
     *
     * @param  resource  $input
     * @return Generator<int, string>
     */
    public function decrypt($input): Generator
    {
        if (self::readUpTo($input, strlen(self::MAGIC)) !== self::MAGIC) {
            throw new RuntimeException('This is not an encrypted Hoshiask backup (the file does not start with HBK1).');
        }

        for ($index = 0; ; $index++) {
            $header = self::readUpTo($input, self::RECORD_HEADER_BYTES);

            if (strlen($header) !== self::RECORD_HEADER_BYTES) {
                throw new RuntimeException('The backup is incomplete: its end is missing (the file was cut short).');
            }

            $flag = ord($header[0]);
            $length = unpack('N', substr($header, 1, 4))[1];

            if ($flag > 1 || $length > self::CHUNK_BYTES) {
                throw new RuntimeException("The backup is damaged at piece {$index}.");
            }

            $ciphertext = self::readUpTo($input, $length);
            if (strlen($ciphertext) !== $length) {
                throw new RuntimeException('The backup is incomplete: its end is missing (the file was cut short).');
            }

            $plain = openssl_decrypt($ciphertext, self::CIPHER, $this->key, OPENSSL_RAW_DATA, substr($header, 5, self::IV_BYTES), substr($header, 5 + self::IV_BYTES, self::TAG_BYTES), self::aad($index, $flag === 1));

            if ($plain === false) {
                throw new RuntimeException("The backup cannot be opened at piece {$index}: the key is wrong, or the file was changed or damaged.");
            }

            yield $plain;

            if ($flag === 1) {
                if (! feof($input) && fread($input, 1) !== '') {
                    throw new RuntimeException('The backup has unexpected bytes after its end.');
                }

                return;
            }
        }
    }

    private static function aad(int $index, bool $last): string
    {
        return 'HBK1'.pack('J', $index).($last ? "\x01" : "\x00");
    }

    /**
     * @param  resource  $stream
     */
    private static function readUpTo($stream, int $length): string
    {
        $data = '';

        while (strlen($data) < $length && ! feof($stream)) {
            $part = fread($stream, $length - strlen($data));

            if ($part === false || $part === '') {
                break;
            }

            $data .= $part;
        }

        return $data;
    }

    /**
     * @param  resource  $stream
     */
    private static function write($stream, string $bytes): void
    {
        while ($bytes !== '') {
            $written = fwrite($stream, $bytes);

            if ($written === false || $written === 0) {
                throw new RuntimeException('Could not write the backup file (is the disk full?).');
            }

            $bytes = substr($bytes, $written);
        }
    }
}
