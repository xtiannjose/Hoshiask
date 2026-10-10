<?php

namespace Tests\Unit;

use App\Rules\PhoneNumber;
use App\Support\UploadLimits;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class UploadLimitsTest extends TestCase
{
    /**
     * @return array<string, array{0: string, 1: int}>
     */
    public static function sizes(): array
    {
        return [
            'megabytes' => ['2M', 2 * 1024 * 1024],
            'lowercase' => ['8m', 8 * 1024 * 1024],
            'kilobytes' => ['512K', 512 * 1024],
            'gigabytes' => ['1G', 1024 * 1024 * 1024],
            'plain bytes' => ['1048576', 1048576],
            'unlimited' => ['-1', 0],
            'empty' => ['', 0],
        ];
    }

    #[DataProvider('sizes')]
    public function test_php_ini_sizes_are_converted_to_bytes(string $ini, int $bytes): void
    {
        $this->assertSame($bytes, UploadLimits::parseSize($ini));
    }

    public function test_sizes_are_formatted_for_people(): void
    {
        $this->assertSame('2 MB', UploadLimits::format(2 * 1024 * 1024));
        $this->assertSame('10 MB', UploadLimits::format(10 * 1024 * 1024));
        $this->assertSame('6.3 MB', UploadLimits::format((int) (6.25 * 1024 * 1024)));
        $this->assertSame('300 KB', UploadLimits::format(300 * 1024));
    }

    public function test_the_per_file_limit_never_exceeds_what_the_form_promises(): void
    {
        $this->assertLessThanOrEqual(UploadLimits::APP_MAX_KB * 1024, UploadLimits::perFileBytes());
        $this->assertGreaterThan(0, UploadLimits::perFileBytes());
    }

    /**
     * @return array<string, array{0: string, 1: bool}>
     */
    public static function phoneNumbers(): array
    {
        return [
            'mobile' => ['09171234567', true],
            'international' => ['+63 917 123 4567', true],
            'landline with area code' => ['(02) 8123-4567', true],
            'dashes' => ['0917-123-4567', true],
            'letters' => ['abc', false],
            'too short' => ['12345', false],
            'digits with letters' => ['0917 CALL ME', false],
            'symbols' => ['0917#1234567', false],
            'too long' => ['12345678901234567890', false],
        ];
    }

    #[DataProvider('phoneNumbers')]
    public function test_phone_numbers_must_be_dialable(string $number, bool $valid): void
    {
        $validator = Validator::make(['phone' => $number], ['phone' => [new PhoneNumber]]);

        $this->assertSame($valid, $validator->passes(), $number);
    }

    public function test_a_blank_phone_number_is_left_to_the_required_or_nullable_rule(): void
    {
        $this->assertTrue(Validator::make(['phone' => ''], ['phone' => ['nullable', new PhoneNumber]])->passes());
    }
}
