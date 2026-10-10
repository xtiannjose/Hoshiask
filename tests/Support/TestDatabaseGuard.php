<?php

namespace Tests\Support;

use RuntimeException;

/**
 * Refuses to run the suite against a real database. RefreshDatabase runs migrate:fresh, so a test run that
 * ignores phpunit.xml (an IDE runner, a path argument) with a PostgreSQL .env would wipe that database.
 */
final class TestDatabaseGuard
{
    public static function assertSafe(?string $connection, ?string $database): void
    {
        $inMemory = $connection === 'sqlite' && in_array($database, [':memory:', ''], true);
        $disposable = $database !== null && str_ends_with($database, '_test');

        if ($inMemory || $disposable) {
            return;
        }

        throw new RuntimeException(sprintf(
            'Refusing to run tests on connection "%s", database "%s". Run them with `php artisan test` or `vendor/bin/phpunit -c phpunit.xml` '
            .'(SQLite in memory), or point the connection at a database whose name ends in "_test".',
            $connection ?? 'null',
            $database ?? 'null',
        ));
    }
}
