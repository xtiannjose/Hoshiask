<?php

namespace Tests\Unit;

use App\Services\Backup\TarArchive;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class TarArchiveTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dir = sys_get_temp_dir().'/tar-test-'.bin2hex(random_bytes(4));
        File::ensureDirectoryExists($this->dir);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dir);

        parent::tearDown();
    }

    /**
     * @param  array<string, string>  $files  name => contents
     */
    private function build(array $files): string
    {
        $tarPath = $this->dir.'/test.tar';
        $handle = fopen($tarPath, 'wb');
        $tar = new TarArchive($handle);
        foreach ($files as $name => $contents) {
            $source = $this->dir.'/src-'.md5($name);
            file_put_contents($source, $contents);
            $added = $tar->addFile($name, $source);
            $this->assertSame(['bytes' => strlen($contents), 'sha256' => hash('sha256', $contents)], $added);
        }
        $tar->addString('manifest.json', '{"ok":true}');
        $tar->close();
        fclose($handle);

        return $tarPath;
    }

    /**
     * @return array<string, string>
     */
    private function readBack(string $bytes, int $pieceSize = 7000): array
    {
        $found = [];
        TarArchive::read(str_split($bytes, $pieceSize), function (string $name, int $size, iterable $content) use (&$found): void {
            $found[$name] = implode('', iterator_to_array($content, false));
            $this->assertSame($size, strlen($found[$name]));
        });

        return $found;
    }

    private function files(): array
    {
        return [
            'database/dump.sql.gz' => random_bytes(1048576 + 300),
            'files/attachments/empty.txt' => '',
            'files/attachments/exactly-512.bin' => random_bytes(512),
            'files/attachments/'.str_repeat('deep/', 25).'long-name-'.str_repeat('x', 60).'.pdf' => 'nested',
        ];
    }

    public function test_files_come_back_exactly_with_names_longer_than_100_characters(): void
    {
        $files = $this->files();

        $this->assertSame($files + ['manifest.json' => '{"ok":true}'], $this->readBack((string) file_get_contents($this->build($files))));
    }

    public function test_a_callback_that_reads_nothing_still_moves_on_to_the_next_file(): void
    {
        $names = [];
        TarArchive::read([(string) file_get_contents($this->build($this->files()))], function (string $name) use (&$names): void {
            $names[] = $name;
        });

        $this->assertSame([...array_keys($this->files()), 'manifest.json'], $names);
    }

    public function test_the_system_tar_program_reads_the_archive(): void
    {
        $tar = (new ExecutableFinder)->find('tar');
        if ($tar === null) {
            $this->markTestSkipped('No tar program on this computer.');
        }

        $files = $this->files();
        $tarPath = $this->build($files);
        $out = $this->dir.'/out';
        File::ensureDirectoryExists($out);
        $process = new Process([$tar, '-xf', basename($tarPath), '-C', 'out'], $this->dir);
        $process->run();

        $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
        foreach ($files as $name => $contents) {
            $this->assertSame($contents, file_get_contents($out.'/'.$name), $name);
        }
    }

    public function test_a_damaged_header_is_detected(): void
    {
        $bytes = (string) file_get_contents($this->build(['a.txt' => 'hello']));
        $bytes[10] = 'Z';

        $this->expectExceptionMessage('damaged');
        $this->readBack($bytes);
    }

    public function test_an_archive_cut_short_is_detected(): void
    {
        $bytes = (string) file_get_contents($this->build(['a.txt' => str_repeat('a', 3000)]));

        $this->expectExceptionMessage('cut short');
        $this->readBack(substr($bytes, 0, 1500));
    }
}
