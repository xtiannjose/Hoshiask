<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Storage;
use Tests\Support\ImportFiles;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

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
