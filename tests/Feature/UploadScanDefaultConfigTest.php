<?php

namespace Tests\Feature;

use Tests\TestCase;
use Tests\Traits\ChangesEnvironment;

class UploadScanDefaultConfigTest extends TestCase
{
    use ChangesEnvironment;

    private function driver(?string $setting, string $appEnv): string
    {
        $this->setEnvironmentValue('HOSHIASK_UPLOAD_SCAN', $setting);
        $this->setEnvironmentValue('APP_ENV', $appEnv);

        $config = require config_path('security.php');

        return $config['upload_scan']['driver'];
    }

    public function test_scanning_is_on_by_default_in_production_and_off_elsewhere(): void
    {
        $this->assertSame('clamav', $this->driver(null, 'production'));
        $this->assertSame('clamav', $this->driver('', 'production'));
        $this->assertSame('off', $this->driver('', 'local'));
        $this->assertSame('off', $this->driver(null, 'testing'));
    }

    public function test_an_explicit_setting_wins_in_both_directions(): void
    {
        $this->assertSame('off', $this->driver('off', 'production'));
        $this->assertSame('clamav', $this->driver('clamav', 'local'));
        $this->assertSame('clamav', $this->driver('  CLAMAV ', 'local'));
    }
}
