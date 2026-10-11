<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class HelpdeskMigrationBatchCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_prints_only_the_latest_batch_number(): void
    {
        DB::table('migrations')->insert(['migration' => '2099_01_01_000000_pretend_deploy', 'batch' => 99]);

        $this->artisan('helpdesk:migration-batch')
            ->expectsOutput('99')
            ->assertSuccessful();
    }
}
