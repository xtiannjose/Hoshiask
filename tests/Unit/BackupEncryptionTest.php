<?php

namespace Tests\Unit;

use App\Services\Backup\BackupEncryption;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class BackupEncryptionTest extends TestCase
{
    private const HEADER = 5;

    private const RECORD_OVERHEAD = 33;

    private function encrypt(string $plain, ?string $key = null): string
    {
        $in = fopen('php://memory', 'w+b');
        fwrite($in, $plain);
        rewind($in);
        $out = fopen('php://memory', 'w+b');
        (new BackupEncryption($key ?? $this->key()))->encrypt($in, $out);
        rewind($out);

        return (string) stream_get_contents($out);
    }

    private function decrypt(string $encrypted, ?string $key = null): string
    {
        $in = fopen('php://memory', 'w+b');
        fwrite($in, $encrypted);
        rewind($in);

        return implode('', iterator_to_array((new BackupEncryption($key ?? $this->key()))->decrypt($in), false));
    }

    private function key(): string
    {
        static $key;

        return $key ??= BackupEncryption::generateKey();
    }

    /**
     * @return array<string, array{0: int}>
     */
    public static function sizes(): array
    {
        return ['empty' => [0], 'one byte' => [1], 'exactly 1 MiB' => [1048576], '1 MiB and one byte' => [1048577], 'several MiB' => [3 * 1048576 + 17]];
    }

    #[DataProvider('sizes')]
    public function test_what_goes_in_comes_out(int $size): void
    {
        $plain = $size > 0 ? random_bytes($size) : '';
        $encrypted = $this->encrypt($plain);

        $this->assertStringStartsWith(BackupEncryption::MAGIC, $encrypted);
        $this->assertSame($plain, $this->decrypt($encrypted));
        $records = max(1, (int) ceil($size / 1048576));
        $this->assertSame(self::HEADER + $records * self::RECORD_OVERHEAD + $size, strlen($encrypted), 'Encrypting adds only a few bytes per MiB.');
    }

    public function test_the_wrong_key_is_refused(): void
    {
        $this->expectExceptionMessage('the key is wrong, or the file was changed or damaged');

        $this->decrypt($this->encrypt('secret'), BackupEncryption::generateKey());
    }

    public function test_one_changed_byte_is_detected(): void
    {
        $encrypted = $this->encrypt(random_bytes(5000));
        $encrypted[3000] = chr(ord($encrypted[3000]) ^ 1);

        $this->expectExceptionMessage('changed or damaged');
        $this->decrypt($encrypted);
    }

    public function test_a_file_cut_exactly_between_two_pieces_is_detected(): void
    {
        $encrypted = $this->encrypt(random_bytes(2 * 1048576 + 10));
        $firstRecordEnd = self::HEADER + self::RECORD_OVERHEAD + 1048576;

        $this->expectExceptionMessage('its end is missing');
        $this->decrypt(substr($encrypted, 0, $firstRecordEnd));
    }

    public function test_a_file_cut_in_the_middle_is_detected(): void
    {
        $this->expectExceptionMessage('its end is missing');
        $this->decrypt(substr($this->encrypt(random_bytes(2000)), 0, -10));
    }

    public function test_bytes_after_the_end_are_detected(): void
    {
        $this->expectExceptionMessage('unexpected bytes after its end');
        $this->decrypt($this->encrypt('abc').'x');
    }

    public function test_swapped_pieces_are_detected(): void
    {
        $size = 1048576;
        $encrypted = $this->encrypt(random_bytes(3 * $size));
        $record = self::RECORD_OVERHEAD + $size;
        $first = substr($encrypted, self::HEADER, $record);
        $second = substr($encrypted, self::HEADER + $record, $record);
        $swapped = BackupEncryption::MAGIC.$second.$first.substr($encrypted, self::HEADER + 2 * $record);

        $this->expectExceptionMessage('cannot be opened at piece 0');
        $this->decrypt($swapped);
    }

    public function test_a_repeated_piece_is_detected(): void
    {
        $size = 1048576;
        $encrypted = $this->encrypt(random_bytes(2 * $size));
        $record = self::RECORD_OVERHEAD + $size;
        $first = substr($encrypted, self::HEADER, $record);

        $this->expectExceptionMessage('cannot be opened at piece 1');
        $this->decrypt(BackupEncryption::MAGIC.$first.$first.substr($encrypted, self::HEADER + $record));
    }

    public function test_something_that_is_not_a_backup_is_refused(): void
    {
        $this->expectExceptionMessage('does not start with HBK1');
        $this->decrypt('PK just a zip');
    }

    public function test_a_badly_formed_key_is_refused(): void
    {
        $this->expectException(RuntimeException::class);
        new BackupEncryption('not-a-key');
    }

    public function test_the_standalone_script_decrypts_what_the_app_encrypted(): void
    {
        $plain = random_bytes(1048576 + 4321);
        $encryptedPath = tempnam(sys_get_temp_dir(), 'enc');
        $outputPath = tempnam(sys_get_temp_dir(), 'dec');
        file_put_contents($encryptedPath, $this->encrypt($plain));

        try {
            $process = new Process([PHP_BINARY, base_path('scripts/helpdesk-backup-decrypt.php'), $encryptedPath, $outputPath, $this->key()]);
            $process->run();
            $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
            $this->assertSame($plain, file_get_contents($outputPath));

            $wrongKey = new Process([PHP_BINARY, base_path('scripts/helpdesk-backup-decrypt.php'), $encryptedPath, $outputPath, BackupEncryption::generateKey()]);
            $wrongKey->run();
            $this->assertFalse($wrongKey->isSuccessful());
            $this->assertStringContainsString('the key is wrong', $wrongKey->getErrorOutput());
            $this->assertFileDoesNotExist($outputPath, 'A failed decryption leaves no half-written file behind.');
        } finally {
            @unlink($encryptedPath);
            @unlink($outputPath);
        }
    }
}
