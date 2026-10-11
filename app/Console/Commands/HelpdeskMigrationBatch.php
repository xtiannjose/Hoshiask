<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class HelpdeskMigrationBatch extends Command
{
    protected $signature = 'helpdesk:migration-batch';

    protected $description = 'Print the number of the latest migration batch, or 0 when nothing has run. The deploy script uses it to know which migrations a deploy applied.';

    public function handle(): int
    {
        $this->line((string) (Schema::hasTable('migrations') ? (int) DB::table('migrations')->max('batch') : 0));

        return self::SUCCESS;
    }
}
