<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class HelpdeskBackupDatabaseCommandTest extends TestCase
{
    public function test_backup_command_exports_file_backed_sqlite_database(): void
    {
        $databasePath = database_path('backup-command-test.sqlite');
        $backupDirectory = storage_path('app/private/backup-command-test');

        File::delete($databasePath);
        File::deleteDirectory($backupDirectory);
        File::put($databasePath, 'sqlite backup fixture');

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', $databasePath);
        config()->set('helpdesk.backup.disk', 'local');
        config()->set('helpdesk.backup.path', 'backup-command-test');

        try {
            $this->artisan('helpdesk:backup-database')
                ->expectsOutput('Database backup created.')
                ->assertSuccessful();

            $backups = File::glob($backupDirectory.DIRECTORY_SEPARATOR.'sqlite-*.sqlite') ?: [];

            $this->assertCount(1, $backups);
            $this->assertSame('sqlite backup fixture', File::get($backups[0]));
        } finally {
            File::delete($databasePath);
            File::deleteDirectory($backupDirectory);
        }
    }
}
