<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Tests\Support\TestDatabaseGuard;

trait CreatesApplication
{
    public function createApplication()
    {
        $app = require __DIR__.'/../bootstrap/app.php';

        $app->make(Kernel::class)->bootstrap();

        $connection = $app['config']->get('database.default');
        TestDatabaseGuard::assertSafe($connection, $app['config']->get("database.connections.{$connection}.database"));

        return $app;
    }
}
