<?php

namespace App\Console\Commands;

use App\Services\Backup\BackupEncryption;
use Illuminate\Console\Command;

class HoshiaskBackupKey extends Command
{
    protected $signature = 'hoshiask:backup-key';

    protected $description = 'Print a new key for encrypting backups. It is not saved anywhere.';

    public function handle(): int
    {
        $this->line(BackupEncryption::generateKey());
        $this->newLine();
        $this->line('1. Store this key OUTSIDE this server: a password manager, and a printed copy in a safe.');
        $this->line('2. Without it, the backups cannot be opened. Nobody can recover it for you.');
        $this->line('3. Put it in .env as HOSHIASK_BACKUP_ENCRYPTION_KEY=... and run php artisan config:clear.');
        $this->line('4. Do not reuse APP_KEY or any other password for it.');

        return self::SUCCESS;
    }
}
