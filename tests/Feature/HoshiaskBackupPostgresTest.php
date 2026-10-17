<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Backup\BackupBundle;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\ExecutableFinder;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * Production runs on PostgreSQL: one real pg_dump, bundled and checked. Runs where tests use PostgreSQL and
 * pg_dump is installed (the CI postgres job); skipped elsewhere.
 */
class HoshiaskBackupPostgresTest extends TestCase
{
    use CreatesTestData;

    public function test_a_real_pg_dump_is_bundled_checked_and_counted(): void
    {
        if (config('database.default') !== 'pgsql' || (new ExecutableFinder)->find('pg_dump') === null) {
            $this->markTestSkipped('Needs PostgreSQL and pg_dump.');
        }

        if (! Schema::hasTable('tickets')) {
            $this->artisan('migrate', ['--force' => true])->assertSuccessful();
        }

        Storage::fake('local');
        Storage::fake('department-logos');
        config(['hoshiask.attachments_disk' => 'local', 'hoshiask.backup.encryption_key' => null]);
        $client = $this->createClient('Pg Backup Client', 'pg-backup-client@example.com');
        $category = $this->createCategory('Pg Backup Category');
        $ticket = $this->createTicket($client, $category, ['subject' => "Tab\tand new\nline survive"]);
        $tickets = Ticket::query()->count();

        try {
            $bundles = app(BackupBundle::class);
            $backup = $bundles->create();
            $result = $bundles->verify($backup['path'], null);

            $this->assertSame([], array_filter($result['checks']), json_encode($result['checks']));
            $this->assertSame('pgsql', $result['manifest']['database']['driver']);
            $this->assertSame($tickets, $result['manifest']['database']['tables']['tickets']);
            $this->assertArrayHasKey('migrations', $result['manifest']['database']['tables']);

            // A dump whose end is missing is refused.
            $restored = sys_get_temp_dir().'/pg-restore-'.bin2hex(random_bytes(4));
            $bundles->restore($backup['path'], null, $restored);
            $dump = (string) gzdecode((string) file_get_contents($restored.'/database/dump.sql.gz'));
            $this->assertStringContainsString('COPY public.tickets', $dump);
            $cut = $restored.'/cut.sql.gz';
            file_put_contents($cut, gzencode(substr($dump, 0, (int) strpos($dump, '-- PostgreSQL database dump complete'))));
            $this->assertFalse(BackupBundle::inspectSqlDump($cut, 'pgsql')['complete']);
            File::deleteDirectory($restored);
        } finally {
            DB::table('tickets')->where('id', $ticket->id)->delete();
            Category::query()->whereKey($category->id)->delete();
            User::query()->whereKey($client->id)->delete();
        }
    }
}
