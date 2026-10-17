<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\TestDatabaseGuard;

class TestDatabaseGuardTest extends TestCase
{
    public function test_in_memory_sqlite_and_a_database_ending_in_test_are_allowed(): void
    {
        TestDatabaseGuard::assertSafe('sqlite', ':memory:');
        TestDatabaseGuard::assertSafe('pgsql', 'hoshiask_test');
        $this->addToAssertionCount(2);
    }

    public function test_a_real_database_is_refused(): void
    {
        $this->expectException(RuntimeException::class);

        TestDatabaseGuard::assertSafe('pgsql', 'hoshishi_ticketing');
    }

    public function test_a_sqlite_file_is_refused(): void
    {
        $this->expectException(RuntimeException::class);

        TestDatabaseGuard::assertSafe('sqlite', 'database/database.sqlite');
    }
}
