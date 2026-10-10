<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Storage;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();

        config(['helpdesk.attachments_disk' => 'attachments-testing']);
        Storage::fake('attachments-testing');
        $this->withoutVite();
    }
}
