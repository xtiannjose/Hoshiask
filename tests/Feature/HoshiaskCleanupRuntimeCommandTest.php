<?php

namespace Tests\Feature;

use App\Models\Attachment;
use App\Models\Category;
use App\Models\CredentialHandoff;
use App\Models\SystemLog;
use App\Models\Ticket;
use App\Models\TicketReply;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class HoshiaskCleanupRuntimeCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        foreach ([
            storage_path('app/cleanup-backups'),
            storage_path('app/cleanup-imports'),
            storage_path('app/cleanup-seeded-credentials'),
        ] as $path) {
            File::deleteDirectory($path);
        }
        File::deleteDirectory(storage_path('app/cleanup-local/attachments'));

        parent::tearDown();
    }

    public function test_cleanup_runtime_always_keeps_the_newest_three_backups_even_when_all_are_old(): void
    {
        config()->set('hoshiask.cleanup.backup_retention_days', 14);
        config()->set('hoshiask.backup.disk', 'cleanup-backups');
        config()->set('filesystems.disks.cleanup-backups', [
            'driver' => 'local',
            'root' => storage_path('app/cleanup-backups'),
            'throw' => false,
        ]);

        // Backups have been failing for weeks: every file is older than the retention period.
        foreach (['a' => 60, 'b' => 50, 'c' => 40, 'd' => 30, 'e' => 20] as $name => $daysOld) {
            Storage::disk('cleanup-backups')->put("backups/hoshiask-{$name}.tar.enc", 'x');
            touch(Storage::disk('cleanup-backups')->path("backups/hoshiask-{$name}.tar.enc"), now()->subDays($daysOld)->getTimestamp());
        }

        $this->artisan('hoshiask:cleanup-runtime')->assertSuccessful();

        $this->assertFalse(Storage::disk('cleanup-backups')->exists('backups/hoshiask-a.tar.enc'));
        $this->assertFalse(Storage::disk('cleanup-backups')->exists('backups/hoshiask-b.tar.enc'));
        $this->assertTrue(Storage::disk('cleanup-backups')->exists('backups/hoshiask-c.tar.enc'));
        $this->assertTrue(Storage::disk('cleanup-backups')->exists('backups/hoshiask-d.tar.enc'));
        $this->assertTrue(Storage::disk('cleanup-backups')->exists('backups/hoshiask-e.tar.enc'));
    }

    public function test_cleanup_runtime_removes_transient_artifacts_prunes_stale_handoffs_and_deletes_zero_byte_orphans(): void
    {
        config()->set('observability.system_logs.retention_days', 30);
        config()->set('hoshiask.cleanup.backup_retention_days', 30);
        config()->set('hoshiask.cleanup.seeded_client_credentials_retention_days', 14);
        config()->set('hoshiask.cleanup.import_retention_days', 7);
        config()->set('hoshiask.backup.disk', 'cleanup-backups');
        config()->set('hoshiask.seed_client_credentials_disk', 'cleanup-seeded-credentials');
        config()->set('hoshiask.ticket_import_disk', 'cleanup-imports');
        config()->set('filesystems.disks.cleanup-backups', [
            'driver' => 'local',
            'root' => storage_path('app/cleanup-backups'),
            'throw' => false,
        ]);
        config()->set('filesystems.disks.cleanup-seeded-credentials', [
            'driver' => 'local',
            'root' => storage_path('app/cleanup-seeded-credentials'),
            'throw' => false,
        ]);
        config()->set('filesystems.disks.cleanup-imports', [
            'driver' => 'local',
            'root' => storage_path('app/cleanup-imports'),
            'throw' => false,
        ]);

        File::put(base_path('.phpunit.result.cache'), 'temp-cache');
        File::ensureDirectoryExists(storage_path('phpstan/cache'));
        File::put(storage_path('phpstan/cache/stale.php'), '<?php return [];');
        File::ensureDirectoryExists(storage_path('framework/testing/disks/demo'));
        File::put(storage_path('framework/testing/disks/demo/stale.txt'), 'stale');

        config()->set('hoshiask.attachments_disk', 'cleanup-local');
        config()->set('filesystems.disks.cleanup-local', [
            'driver' => 'local',
            'root' => storage_path('app/cleanup-local'),
            'throw' => false,
        ]);

        Storage::disk('cleanup-local')->put('attachments/orphan-zero.txt', '');
        Storage::disk('cleanup-local')->put('attachments/keep-zero.txt', '');

        $user = User::create([
            'name' => 'Cleanup User',
            'email' => 'cleanup-user@example.com',
            'phone' => '09170000000',
            'department' => 'Hoshishi',
            'role' => User::ROLE_CLIENT,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $category = Category::create([
            'name' => 'Cleanup Category',
            'description' => 'Cleanup category',
            'color' => '#0f8d88',
            'is_active' => true,
        ]);

        $ticket = Ticket::create([
            'name' => 'Cleanup Ticket',
            'contact_number' => '09175550001',
            'email' => 'cleanup-ticket@example.com',
            'province' => 'NCR',
            'municipality' => 'Pasig',
            'subject' => 'Cleanup ticket',
            'description' => 'Cleanup ticket body',
            'priority' => 'medium',
            'status' => 'open',
            'user_id' => $user->id,
            'category_id' => $category->id,
        ]);

        $reply = TicketReply::create([
            'ticket_id' => $ticket->id,
            'user_id' => $user->id,
            'message' => 'Attachment anchor',
            'is_internal' => false,
        ]);

        Attachment::create([
            'filename' => 'keep-zero.txt',
            'original_filename' => 'keep-zero.txt',
            'file_path' => 'attachments/keep-zero.txt',
            'mime_type' => 'text/plain',
            'file_size' => 0,
            'attachable_type' => TicketReply::class,
            'attachable_id' => $reply->id,
        ]);

        $staleHandoff = CredentialHandoff::create([
            'target_user_id' => $user->id,
            'issued_by_user_id' => $user->id,
            'temporary_password' => 'stale-token',
            'expires_at' => Carbon::now()->subMinute(),
            'revealed_at' => null,
            'consumed_at' => null,
        ]);

        Cache::put('managed-user-password-handoff:stale-token', 'secret', now()->addMinutes(10));

        $oldSystemLog = SystemLog::create([
            'category' => 'cleanup',
            'event_type' => 'cleanup.old',
            'description' => 'Old cleanup log',
            'metadata' => ['age' => 'old'],
            'occurred_at' => now()->subDays(45),
        ]);

        $recentSystemLog = SystemLog::create([
            'category' => 'cleanup',
            'event_type' => 'cleanup.recent',
            'description' => 'Recent cleanup log',
            'metadata' => ['age' => 'recent'],
            'occurred_at' => now()->subDays(5),
        ]);

        Storage::disk('cleanup-backups')->put('backups/old-backup.json', '{}');
        Storage::disk('cleanup-backups')->put('backups/recent-backup.json', '{}');
        Storage::disk('cleanup-backups')->put('backups/recent-backup-2.json', '{}');
        Storage::disk('cleanup-backups')->put('backups/recent-backup-3.json', '{}');
        Storage::disk('cleanup-seeded-credentials')->put('seeded-client-credentials/old-export.csv', 'email,password');
        Storage::disk('cleanup-seeded-credentials')->put('seeded-client-credentials/recent-export.csv', 'email,password');
        Storage::disk('cleanup-imports')->put('imports/old-import.csv', 'subject,description');
        Storage::disk('cleanup-imports')->put('imports/recent-import.csv', 'subject,description');

        touch(Storage::disk('cleanup-backups')->path('backups/old-backup.json'), now()->subDays(45)->getTimestamp());
        touch(Storage::disk('cleanup-backups')->path('backups/recent-backup.json'), now()->subDays(5)->getTimestamp());
        touch(Storage::disk('cleanup-backups')->path('backups/recent-backup-2.json'), now()->subDays(6)->getTimestamp());
        touch(Storage::disk('cleanup-backups')->path('backups/recent-backup-3.json'), now()->subDays(7)->getTimestamp());
        touch(Storage::disk('cleanup-seeded-credentials')->path('seeded-client-credentials/old-export.csv'), now()->subDays(20)->getTimestamp());
        touch(Storage::disk('cleanup-seeded-credentials')->path('seeded-client-credentials/recent-export.csv'), now()->subDays(2)->getTimestamp());
        touch(Storage::disk('cleanup-imports')->path('imports/old-import.csv'), now()->subDays(10)->getTimestamp());
        touch(Storage::disk('cleanup-imports')->path('imports/recent-import.csv'), now()->subDays(2)->getTimestamp());

        $this->artisan('hoshiask:cleanup-runtime')
            ->expectsOutput('Hoshiask runtime cleanup complete.')
            ->assertSuccessful();

        $this->assertFileDoesNotExist(base_path('.phpunit.result.cache'));
        $this->assertFileDoesNotExist(storage_path('phpstan/cache/stale.php'));
        $this->assertFileDoesNotExist(storage_path('framework/testing/disks/demo/stale.txt'));
        $this->assertDatabaseMissing('credential_handoffs', [
            'id' => $staleHandoff->id,
        ]);
        $this->assertFalse(Cache::has('managed-user-password-handoff:stale-token'));
        $this->assertFalse(Storage::disk('cleanup-local')->exists('attachments/orphan-zero.txt'));
        $this->assertTrue(Storage::disk('cleanup-local')->exists('attachments/keep-zero.txt'));
        $this->assertDatabaseMissing('system_logs', [
            'id' => $oldSystemLog->id,
        ]);
        $this->assertDatabaseHas('system_logs', [
            'id' => $recentSystemLog->id,
        ]);
        $this->assertFalse(Storage::disk('cleanup-backups')->exists('backups/old-backup.json'));
        $this->assertTrue(Storage::disk('cleanup-backups')->exists('backups/recent-backup.json'));
        $this->assertFalse(Storage::disk('cleanup-seeded-credentials')->exists('seeded-client-credentials/old-export.csv'));
        $this->assertTrue(Storage::disk('cleanup-seeded-credentials')->exists('seeded-client-credentials/recent-export.csv'));
        $this->assertFalse(Storage::disk('cleanup-imports')->exists('imports/old-import.csv'));
        $this->assertTrue(Storage::disk('cleanup-imports')->exists('imports/recent-import.csv'));
    }

    public function test_cleanup_runtime_removes_expired_database_cache_rows_and_keeps_live_ones(): void
    {
        // Rows for keys that are never read again (a search, a filter combination) are not removed by the cache itself.
        config()->set('cache.default', 'database');
        DB::table('cache')->insert([
            ['key' => 'hoshiask-cache-expired', 'value' => 's:1:"x";', 'expiration' => now()->subMinute()->getTimestamp()],
            ['key' => 'hoshiask-cache-live', 'value' => 's:1:"x";', 'expiration' => now()->addHour()->getTimestamp()],
        ]);

        $this->artisan('hoshiask:cleanup-runtime')
            ->expectsOutputToContain('Expired cache entries pruned: 1')
            ->assertSuccessful();

        $this->assertDatabaseMissing('cache', ['key' => 'hoshiask-cache-expired']);
        $this->assertDatabaseHas('cache', ['key' => 'hoshiask-cache-live']);
    }
}
