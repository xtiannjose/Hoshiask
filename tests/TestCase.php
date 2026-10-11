<?php

namespace Tests;

use Database\Seeders\StarterDataSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Storage;
use Tests\Support\ImportFiles;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    /**
     * A fresh database has no departments or categories until the starter data is seeded (migrations no longer
     * insert them), so tests that refresh the database start from the same rows a new installation has.
     */
    protected bool $seed = true;

    protected string $seeder = StarterDataSeeder::class;

    protected function setUp(): void
    {
        parent::setUp();

        config(['helpdesk.attachments_disk' => 'attachments-testing']);
        Storage::fake('attachments-testing');

        // Import files go to a temporary folder, not the application's real import folder (see ImportFiles).
        config([
            'filesystems.disks.import-tests' => ['driver' => 'local', 'root' => ImportFiles::root(), 'throw' => false],
            'helpdesk.ticket_import_disk' => 'import-tests',
        ]);
        $this->withoutVite();
    }
}
