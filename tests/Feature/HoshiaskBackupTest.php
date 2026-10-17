<?php

namespace Tests\Feature;

use App\Events\ProductionAdvisoriesRequested;
use App\Listeners\ReportBackupAdvisories;
use App\Models\SystemLog;
use App\Models\Ticket;
use App\Services\Backup\BackupBundle;
use App\Services\Backup\BackupEncryption;
use App\Services\SystemLogService;
use Illuminate\Console\Scheduling\Event as ScheduledEvent;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use PDO;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

/**
 * Runs the real backup against a small file-backed SQLite database with real files on (fake) disks.
 */
class HoshiaskBackupTest extends TestCase
{
    use CreatesTestData;

    private string $work;

    private string $key;

    protected function setUp(): void
    {
        parent::setUp();

        $this->work = sys_get_temp_dir().'/backup-test-'.bin2hex(random_bytes(4));
        File::ensureDirectoryExists($this->work);
        touch($this->work.'/db.sqlite');
        // Its own SQLite file, also when the suite runs on PostgreSQL: nothing here may touch the shared test database.
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => $this->work.'/db.sqlite']);
        DB::purge('sqlite');
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();

        Storage::fake('local');
        Storage::fake('department-logos');
        config(['hoshiask.attachments_disk' => 'local', 'hoshiask.backup.disk' => 'local', 'hoshiask.backup.path' => 'backups']);
        $this->key = BackupEncryption::generateKey();
        config(['hoshiask.backup.encryption_key' => $this->key, 'hoshiask.backup.copy_path' => null]);

        $this->createTicket($this->createClient(), $this->createCategory(), ['subject' => 'Backed up ticket']);
        Storage::disk('local')->put('attachments/report.pdf', random_bytes(70000));
        Storage::disk('local')->put('attachments/2026/10/photo.jpg', random_bytes(1000));
        Storage::disk('department-logos')->put('juniper-logo.png', 'png bytes');
    }

    protected function tearDown(): void
    {
        DB::disconnect('sqlite');
        File::deleteDirectory($this->work);

        parent::tearDown();
    }

    private function backups(string $pattern = 'hoshiask-*'): array
    {
        return glob(Storage::disk('local')->path('backups').'/'.$pattern) ?: [];
    }

    public function test_an_encrypted_backup_holds_the_database_and_every_file_and_restores_exactly(): void
    {
        $this->artisan('hoshiask:backup')
            ->expectsOutputToContain('Checked: the backup opens and every part matches.')
            ->expectsOutputToContain('Backup complete.')
            ->assertSuccessful();

        $bundles = $this->backups('hoshiask-*.tar.enc');
        $this->assertCount(1, $bundles);
        $this->assertStringStartsWith(BackupEncryption::MAGIC, (string) file_get_contents($bundles[0], length: 5));
        $this->assertSame([], glob(Storage::disk('local')->path('backups').'/.work-*') ?: [], 'The work folder is removed.');

        $restored = $this->work.'/restored';
        $this->artisan('hoshiask:backup-restore', ['file' => $bundles[0], 'directory' => $restored])
            ->expectsOutputToContain('Nothing in the running system was changed.')
            ->assertSuccessful();

        $this->assertSame(Storage::disk('local')->get('attachments/report.pdf'), file_get_contents($restored.'/files/attachments/report.pdf'));
        $this->assertSame(Storage::disk('local')->get('attachments/2026/10/photo.jpg'), file_get_contents($restored.'/files/attachments/2026/10/photo.jpg'));
        $this->assertSame('png bytes', file_get_contents($restored.'/files/department-logos/juniper-logo.png'));

        file_put_contents($restored.'/db.sqlite', gzdecode((string) file_get_contents($restored.'/database/database.sqlite.gz')));
        $pdo = new PDO('sqlite:'.$restored.'/db.sqlite');
        $this->assertSame('Backed up ticket', $pdo->query('SELECT subject FROM tickets')->fetchColumn());
        $pdo = null;

        $manifest = json_decode((string) file_get_contents($restored.'/manifest.json'), true);
        $this->assertSame(1, $manifest['format_version']);
        $this->assertSame('sqlite', $manifest['database']['driver']);
        $this->assertSame(1, $manifest['database']['tables']['tickets']);
        $this->assertArrayHasKey('system_logs', $manifest['database']['tables']);
        $this->assertSame(3, $manifest['files']['count']);
        $this->assertSame(70000 + 1000 + 9, $manifest['files']['bytes']);
        $this->assertContains(['path' => 'files/department-logos/juniper-logo.png', 'bytes' => 9, 'sha256' => hash('sha256', 'png bytes')], $manifest['files']['items']);

        $log = SystemLog::query()->where('event_type', 'backup.completed')->firstOrFail();
        $this->assertSame(basename($bundles[0]), $log->metadata['file']);
        $this->assertTrue($log->metadata['encrypted']);
        $this->assertTrue($log->metadata['verified']);
        $this->assertFalse($log->metadata['copied']);
        $this->assertSame(3, $log->metadata['file_count']);
        $this->assertStringNotContainsString(substr($this->key, 7), json_encode(SystemLog::query()->get()->toArray()));
    }

    public function test_verify_checks_the_newest_backup_and_logs_the_result(): void
    {
        $this->artisan('hoshiask:backup')->assertSuccessful();

        $this->artisan('hoshiask:backup-verify')
            ->expectsOutputToContain('This backup is complete and usable.')
            ->assertSuccessful();
        $this->artisan('hoshiask:backup-verify', ['file' => basename($this->backups()[0])])->assertSuccessful();
        $this->assertSame(2, SystemLog::query()->where('event_type', 'backup.verified')->count());

        $this->artisan('hoshiask:backup-verify', ['--key' => BackupEncryption::generateKey()])
            ->expectsOutputToContain('the key is wrong')
            ->expectsOutputToContain('This backup is NOT usable.')
            ->assertFailed();
        $this->assertSame(1, SystemLog::query()->where('event_type', 'backup.verify_failed')->count());

        $this->artisan('hoshiask:backup-verify', ['file' => 'hoshiask-nope.tar.enc'])->expectsOutputToContain('No backup file named')->assertFailed();
    }

    public function test_a_changed_file_inside_an_unencrypted_backup_is_detected(): void
    {
        config(['hoshiask.backup.encryption_key' => null]);
        $this->artisan('hoshiask:backup')->expectsOutputToContain('is NOT encrypted')->assertSuccessful();
        $bundle = $this->backups('hoshiask-*.tar')[0];

        $bytes = (string) file_get_contents($bundle);
        $at = strpos($bytes, 'png bytes');
        $bytes[$at] = 'P';
        file_put_contents($bundle, $bytes);

        $this->artisan('hoshiask:backup-verify')
            ->expectsOutputToContain('files/department-logos/juniper-logo.png does not match')
            ->assertFailed();
    }

    public function test_a_damaged_encrypted_backup_is_detected(): void
    {
        $this->artisan('hoshiask:backup')->assertSuccessful();
        $bundle = $this->backups()[0];
        $bytes = (string) file_get_contents($bundle);
        $bytes[100] = chr(ord($bytes[100]) ^ 1);
        file_put_contents($bundle, $bytes);

        $this->artisan('hoshiask:backup-verify')->expectsOutputToContain('changed or damaged')->assertFailed();
    }

    public function test_an_unencrypted_backup_is_made_with_a_warning_and_never_copied_away(): void
    {
        config(['hoshiask.backup.encryption_key' => null, 'hoshiask.backup.copy_path' => $this->work.'/copies']);
        File::ensureDirectoryExists($this->work.'/copies');

        $this->artisan('hoshiask:backup')
            ->expectsOutputToContain('is NOT encrypted')
            ->expectsOutputToContain('an unencrypted backup is never copied away')
            ->assertSuccessful();

        $this->assertCount(1, $this->backups('hoshiask-*.tar'));
        $this->assertSame([], glob($this->work.'/copies/*') ?: []);
        $this->assertFalse(SystemLog::query()->where('event_type', 'backup.completed')->firstOrFail()->metadata['encrypted']);
    }

    public function test_an_encrypted_backup_is_copied_checked_and_old_copies_are_pruned(): void
    {
        $copies = $this->work.'/copies';
        File::ensureDirectoryExists($copies);
        config(['hoshiask.backup.copy_path' => $copies]);
        foreach (['hoshiask-20200101-024000.tar.enc', 'hoshiask-20200102-024000.tar', 'someone-elses-file.tar.enc'] as $old) {
            file_put_contents($copies.'/'.$old, 'old');
            touch($copies.'/'.$old, now()->subDays(30)->getTimestamp());
        }

        $this->artisan('hoshiask:backup')->expectsOutputToContain('removed 2 old copies')->assertSuccessful();

        $bundle = $this->backups()[0];
        $this->assertFileEquals($bundle, $copies.'/'.basename($bundle));
        $this->assertSame([basename($bundle), 'someone-elses-file.tar.enc'], array_map('basename', glob($copies.'/*') ?: []));
        $this->assertTrue(SystemLog::query()->where('event_type', 'backup.completed')->firstOrFail()->metadata['copied']);

        $this->artisan('hoshiask:backup-verify', ['--copy' => true])->assertSuccessful();
    }

    public function test_a_missing_copy_folder_fails_the_run_but_keeps_the_backup(): void
    {
        config(['hoshiask.backup.copy_path' => $this->work.'/not-mounted']);

        $this->artisan('hoshiask:backup')->expectsOutputToContain('does not exist (is the drive mounted?)')->assertFailed();

        $this->assertCount(1, $this->backups());
        $this->assertSame(1, SystemLog::query()->where('event_type', 'backup.failed')->count());
    }

    public function test_files_on_a_remote_disk_are_skipped_and_named(): void
    {
        config(['filesystems.disks.remote-attachments' => ['driver' => 's3', 'bucket' => 'x', 'region' => 'x'], 'hoshiask.attachments_disk' => 'remote-attachments']);

        $this->artisan('hoshiask:backup')->expectsOutputToContain('Skipped attachments')->assertSuccessful();

        $result = app(BackupBundle::class)->verify($this->backups()[0], $this->key);
        $this->assertSame(['attachments'], $result['manifest']['files']['skipped_disks']);
        $this->assertSame(1, $result['manifest']['files']['count']);
    }

    public function test_a_failure_is_logged_and_the_run_fails(): void
    {
        config(['database.connections.sqlite.database' => $this->work.'/missing.sqlite']);

        $this->artisan('hoshiask:backup')->expectsOutputToContain('Backup failed: SQLite backup requires a file-backed database.')->assertFailed();

        config(['database.connections.sqlite.database' => $this->work.'/db.sqlite']);
        $this->assertSame('SQLite backup requires a file-backed database.', SystemLog::query()->where('event_type', 'backup.failed')->firstOrFail()->metadata['message']);
        $this->assertSame([], $this->backups());
    }

    public function test_two_backups_cannot_run_at_once(): void
    {
        $lock = Cache::lock('hoshiask:backup', 60);
        $lock->get();

        $this->artisan('hoshiask:backup')->expectsOutputToContain('Another backup is still running')->assertFailed();
        $lock->release();

        $this->assertSame([], $this->backups());
    }

    public function test_restore_refuses_a_folder_that_is_not_empty(): void
    {
        $this->artisan('hoshiask:backup')->assertSuccessful();
        File::ensureDirectoryExists($this->work.'/busy');
        file_put_contents($this->work.'/busy/keep.txt', 'mine');

        $this->artisan('hoshiask:backup-restore', ['file' => $this->backups()[0], 'directory' => $this->work.'/busy'])
            ->expectsOutputToContain('is not empty')
            ->assertFailed();
        $this->assertSame(['keep.txt'], array_map('basename', glob($this->work.'/busy/*') ?: []));
    }

    public function test_the_key_command_prints_a_32_byte_key_and_the_rules_for_keeping_it(): void
    {
        $this->artisan('hoshiask:backup-key')
            ->expectsOutputToContain('Store this key OUTSIDE this server')
            ->expectsOutputToContain('HOSHIASK_BACKUP_ENCRYPTION_KEY')
            ->assertSuccessful();

        $this->assertSame(32, strlen(BackupEncryption::parseKey(BackupEncryption::generateKey())));
    }

    public function test_the_cleanup_command_removes_old_bundles(): void
    {
        $this->artisan('hoshiask:backup')->assertSuccessful();
        $bundle = $this->backups()[0];
        touch($bundle, now()->subDays(20)->getTimestamp());

        $this->artisan('hoshiask:cleanup-runtime')->assertSuccessful();

        $this->assertFileDoesNotExist($bundle);
    }

    public function test_the_nightly_backup_and_the_weekly_check_are_scheduled(): void
    {
        $events = collect(app(Schedule::class)->events());
        $find = fn (string $command): ?ScheduledEvent => $events->first(fn (ScheduledEvent $event) => preg_match('/artisan["\']? '.preg_quote($command, '/').'$/', (string) $event->command) === 1);

        $this->assertSame('40 2 * * *', $find('hoshiask:backup')?->expression);
        $this->assertSame('20 4 * * 0', $find('hoshiask:backup-verify')?->expression);
        $this->assertNull($find('hoshiask:backup-database'), 'The nightly job now makes the full backup.');
    }

    public function test_the_status_check_names_every_backup_problem(): void
    {
        $advisories = function (): string {
            // Only this feature's notes; other features add their own to the same event.
            $request = new ProductionAdvisoriesRequested;
            (new ReportBackupAdvisories)->handle($request);

            return implode("\n", $request->advisories());
        };
        $log = function (string $event, array $metadata = [], int $hoursAgo = 0): void {
            app(SystemLogService::class)->record($event, 'x', ['category' => 'system', 'metadata' => $metadata]);
            SystemLog::query()->latest('id')->firstOrFail()->update(['occurred_at' => now()->subHours($hoursAgo)]);
        };

        $this->assertStringContainsString('No backup has been made yet.', $advisories());
        $this->assertStringContainsString('Backups exist only on this server', $advisories());

        $log('backup.completed', ['encrypted' => false], 30);
        $text = $advisories();
        $this->assertStringContainsString('The last backup was made 1 day ago', $text);
        $this->assertStringContainsString('The latest backup is NOT encrypted', $text);
        $this->assertStringContainsString('No backup has been checked in the last 10 days', $text);

        $log('backup.completed', ['encrypted' => true]);
        $log('backup.verify_failed');
        $this->assertStringContainsString('The last backup check FAILED', $advisories());

        $log('backup.verified');
        config(['hoshiask.backup.copy_path' => '/mnt/backups']);
        $this->assertSame('', $advisories());

        config(['hoshiask.backup.copy_path' => null, 'hoshiask.backup.offsite_confirmed' => true]);
        $this->assertSame('', $advisories());
    }

    public function test_tickets_are_counted_from_the_snapshot_not_from_the_live_database(): void
    {
        $result = app(BackupBundle::class)->create();
        Ticket::query()->delete();

        $verify = app(BackupBundle::class)->verify($result['path'], $this->key);
        $this->assertSame([], array_filter($verify['checks']));
        $this->assertSame(1, $verify['manifest']['database']['tables']['tickets']);
    }
}
