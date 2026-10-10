<?php

namespace Tests\Unit;

use App\Services\Security\ClamAvScanner;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

/**
 * The scanner is checked against both ends of a socket pair: the test plays clamd by writing a reply
 * into one end beforehand and reading back, byte for byte, what the scanner sent into the other.
 */
class ClamAvScannerTest extends TestCase
{
    /** @var resource|null The end of the socket pair that plays clamd. */
    private $clamd;

    /** @var list<string> */
    private array $tempFiles = [];

    protected function tearDown(): void
    {
        if (is_resource($this->clamd)) {
            fclose($this->clamd);
        }

        foreach ($this->tempFiles as $file) {
            @unlink($file);
        }

        parent::tearDown();
    }

    /**
     * A scanner connected to one end of a socket pair, with $reply already waiting in the other end.
     */
    private function scannerAnswering(string $reply, bool $hangUp = false): ClamAvScanner
    {
        // Windows cannot make a Unix socket pair; a loopback pair behaves the same here.
        $pair = stream_socket_pair(DIRECTORY_SEPARATOR === '\\' ? STREAM_PF_INET : STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        $this->assertIsArray($pair, 'Could not create a socket pair.');

        [$client, $this->clamd] = $pair;
        stream_set_timeout($this->clamd, 3);
        fwrite($this->clamd, $reply);

        if ($hangUp) {
            fclose($this->clamd);
            $this->clamd = null;
        }

        return new ClamAvScanner(fn (float $timeout) => $client);
    }

    /** Everything the scanner sent. It closes its end when done, so this stops instead of waiting. */
    private function sentToClamd(): string
    {
        return (string) stream_get_contents($this->clamd);
    }

    private function tempFile(string $contents): string
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'scan');
        file_put_contents($path, $contents);
        $this->tempFiles[] = $path;

        return $path;
    }

    private function neverConnects(): ClamAvScanner
    {
        return new ClamAvScanner(fn () => throw new RuntimeException('Connection refused'));
    }

    // ---- what the scanner says -------------------------------------------------------

    public function test_ok_means_the_file_is_clean(): void
    {
        $result = $this->scannerAnswering("stream: OK\0")->scan($this->tempFile('hello'));

        $this->assertTrue($result->isClean());
        $this->assertFalse($result->isInfected());
        $this->assertFalse($result->isError());
    }

    public function test_found_means_infected_and_names_the_virus(): void
    {
        $result = $this->scannerAnswering("stream: Win.Test.Example-1 FOUND\0")->scan($this->tempFile('hello'));

        $this->assertTrue($result->isInfected());
        $this->assertFalse($result->isClean());
        $this->assertSame('Win.Test.Example-1', $result->signature);
    }

    public function test_an_error_reply_is_an_error_that_repeats_the_scanners_words(): void
    {
        $result = $this->scannerAnswering("INSTREAM size limit exceeded. ERROR\0")->scan($this->tempFile('hello'));

        $this->assertTrue($result->isError());
        $this->assertFalse($result->isClean());
        $this->assertStringContainsString('INSTREAM size limit exceeded. ERROR', (string) $result->errorMessage);
    }

    public function test_a_reply_that_never_gets_its_nul_byte_is_an_error_not_a_clean_file(): void
    {
        $result = $this->scannerAnswering('stream: OK', hangUp: true)->scan($this->tempFile('hello'));

        $this->assertTrue($result->isError());
        // Linux still accepts the file and then finds the reply cut short; Windows already refuses the file.
        $this->assertContains($result->errorMessage, [
            'The scanner closed the connection before it finished its reply.',
            'The scanner stopped accepting the file.',
        ]);
    }

    public function test_an_empty_reply_is_an_error(): void
    {
        $result = $this->scannerAnswering("\0")->scan($this->tempFile('hello'));

        $this->assertTrue($result->isError());
        $this->assertStringContainsString('unexpected answer', (string) $result->errorMessage);
    }

    public function test_a_scanner_that_never_answers_is_an_error_after_the_timeout(): void
    {
        config(['security.upload_scan.timeout' => 0.3]);
        $scanner = $this->scannerAnswering('');

        $started = microtime(true);
        $result = $scanner->scan($this->tempFile('hello'));

        $this->assertTrue($result->isError());
        $this->assertSame('The scanner did not reply in time.', $result->errorMessage);
        $this->assertLessThan(5, microtime(true) - $started);
    }

    public function test_a_refused_connection_is_an_error(): void
    {
        $result = $this->neverConnects()->scan($this->tempFile('hello'));

        $this->assertTrue($result->isError());
        $this->assertSame('Connection refused', $result->errorMessage);
    }

    public function test_a_file_that_cannot_be_read_is_an_error_and_the_scanner_is_not_even_called(): void
    {
        $connections = 0;
        $scanner = new ClamAvScanner(function () use (&$connections) {
            $connections++;

            throw new RuntimeException('should not be reached');
        });

        $result = $scanner->scan(sys_get_temp_dir().DIRECTORY_SEPARATOR.'no-such-file-'.uniqid());

        $this->assertSame('The file could not be read.', $result->errorMessage);
        $this->assertSame(0, $connections);
    }

    // ---- what the scanner sends ------------------------------------------------------

    public function test_it_sends_the_command_then_the_file_with_its_length_then_a_zero_length_end_marker(): void
    {
        $this->scannerAnswering("stream: OK\0")->scan($this->tempFile('hello'));

        $this->assertSame(
            bin2hex("zINSTREAM\0".pack('N', 5).'hello'.pack('N', 0)),
            bin2hex($this->sentToClamd()),
        );
    }

    public function test_an_empty_file_sends_only_the_end_marker(): void
    {
        $result = $this->scannerAnswering("stream: OK\0")->scan($this->tempFile(''));

        $this->assertTrue($result->isClean());
        $this->assertSame(bin2hex("zINSTREAM\0".pack('N', 0)), bin2hex($this->sentToClamd()));
    }

    /**
     * @return array<string, array{0: int}>
     */
    public static function fileSizesAroundTheChunkSize(): array
    {
        return [
            'one byte' => [1],
            'one byte short of a chunk' => [65535],
            'exactly one chunk' => [65536],
            'one byte over a chunk' => [65537],
            'a bit more than one chunk' => [70000],
        ];
    }

    #[DataProvider('fileSizesAroundTheChunkSize')]
    public function test_a_file_is_sent_in_pieces_of_at_most_64_kib_each_with_its_own_length(int $size): void
    {
        $contents = substr(str_repeat('0123456789abcdef', (int) ceil($size / 16)), 0, $size);

        $result = $this->scannerAnswering("stream: OK\0")->scan($this->tempFile($contents));

        $expected = "zINSTREAM\0";
        foreach (str_split($contents, 65536) as $piece) {
            $expected .= pack('N', strlen($piece)).$piece;
        }
        $expected .= pack('N', 0);

        $this->assertTrue($result->isClean());
        $this->assertSame(md5($expected), md5($this->sentToClamd()));
    }

    public function test_a_stream_in_memory_is_scanned_the_same_way_as_a_file(): void
    {
        $memory = fopen('php://memory', 'w+b');
        fwrite($memory, 'in memory');
        rewind($memory);

        $result = $this->scannerAnswering("stream: Some.Virus FOUND\0")->scanStream($memory);
        fclose($memory);

        $this->assertSame('Some.Virus', $result->signature);
        $this->assertSame(bin2hex("zINSTREAM\0".pack('N', 9).'in memory'.pack('N', 0)), bin2hex($this->sentToClamd()));
    }

    // ---- ping ------------------------------------------------------------------------

    public function test_ping_sends_zping_and_pong_means_the_scanner_is_there(): void
    {
        $problem = $this->scannerAnswering("PONG\0")->ping();

        $this->assertNull($problem);
        $this->assertSame("zPING\0", $this->sentToClamd());
    }

    public function test_ping_says_what_is_wrong_when_the_answer_is_not_pong(): void
    {
        $problem = $this->scannerAnswering("UNKNOWN COMMAND\0")->ping();

        $this->assertSame('The scanner answered "UNKNOWN COMMAND" instead of PONG.', $problem);
    }

    public function test_ping_reports_a_refused_connection(): void
    {
        $this->assertSame('Connection refused', $this->neverConnects()->ping());
    }

    public function test_ping_gives_up_after_its_own_short_timeout(): void
    {
        $scanner = $this->scannerAnswering('');

        $started = microtime(true);
        $problem = $scanner->ping(0.3);

        $this->assertSame('The scanner did not reply in time.', $problem);
        $this->assertLessThan(5, microtime(true) - $started);
    }

    // ---- the real connection, from config --------------------------------------------

    public function test_with_nothing_listening_the_error_names_the_address_it_tried(): void
    {
        $server = stream_socket_server('tcp://127.0.0.1:0');
        $port = (int) substr((string) stream_socket_get_name($server, false), strrpos((string) stream_socket_get_name($server, false), ':') + 1);
        fclose($server);

        config([
            'security.upload_scan.clamav.socket' => null,
            'security.upload_scan.clamav.host' => '127.0.0.1',
            'security.upload_scan.clamav.port' => $port,
        ]);

        $problem = (new ClamAvScanner)->ping();

        $this->assertStringStartsWith('Could not connect to the scanner at tcp://127.0.0.1:'.$port.' (', (string) $problem);
    }

    public function test_a_configured_socket_path_wins_over_host_and_port(): void
    {
        config([
            'security.upload_scan.clamav.socket' => '/nowhere/clamd.ctl',
            'security.upload_scan.clamav.host' => '127.0.0.1',
            'security.upload_scan.clamav.port' => 3310,
        ]);

        $problem = (new ClamAvScanner)->ping();

        $this->assertStringStartsWith('Could not connect to the scanner at unix:///nowhere/clamd.ctl (', (string) $problem);
    }
}
