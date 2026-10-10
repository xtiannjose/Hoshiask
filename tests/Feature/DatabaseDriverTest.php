<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The PostgreSQL job in CI sets CI_EXPECT_DB_DRIVER=pgsql. If the suite quietly fell back to SQLite (the settings in
 * phpunit.xml lose to the shell, or the pgsql driver is missing), every test would still pass and the job would prove
 * nothing, so this fails instead. Elsewhere the variable is not set and the test is skipped.
 */
class DatabaseDriverTest extends TestCase
{
    public function test_the_suite_runs_on_the_database_the_ci_job_promised(): void
    {
        $expected = (string) getenv('CI_EXPECT_DB_DRIVER');

        if ($expected === '') {
            $this->markTestSkipped('CI_EXPECT_DB_DRIVER is not set; only the PostgreSQL CI job sets it.');
        }

        $this->assertSame($expected, DB::connection()->getDriverName(), 'The test suite is not running on the database this CI job is for.');
    }
}
