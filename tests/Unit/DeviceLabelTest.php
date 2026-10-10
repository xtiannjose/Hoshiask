<?php

namespace Tests\Unit;

use App\Support\DeviceLabel;
use PHPUnit\Framework\TestCase;

class DeviceLabelTest extends TestCase
{
    public function test_it_names_common_browsers_and_platforms(): void
    {
        $expectations = [
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36' => 'Chrome on Windows',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36 Edg/130.0.0.0' => 'Edge on Windows',
            'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1' => 'Safari on iPhone',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 14.0; rv:131.0) Gecko/20100101 Firefox/131.0' => 'Firefox on macOS',
            'Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Mobile Safari/537.36' => 'Chrome on Android',
            'Mozilla/5.0 (Linux; Android 13; SM-S911B) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/23.0 Chrome/115.0.0.0 Mobile Safari/537.36' => 'Samsung Internet on Android',
            'Mozilla/5.0 (X11; CrOS x86_64 14541.0.0) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36' => 'Chrome on ChromeOS',
        ];

        foreach ($expectations as $userAgent => $expectedLabel) {
            $this->assertSame($expectedLabel, DeviceLabel::fromUserAgent($userAgent), $userAgent);
        }
    }

    public function test_it_falls_back_for_missing_or_unrecognised_user_agents(): void
    {
        $this->assertSame('Unknown device', DeviceLabel::fromUserAgent(null));
        $this->assertSame('Unknown device', DeviceLabel::fromUserAgent('   '));
        $this->assertSame('Unknown device', DeviceLabel::fromUserAgent('curl/8.4.0'));
    }
}
