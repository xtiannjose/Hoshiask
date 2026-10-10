<?php

namespace Tests\Unit;

use App\Support\DuplicateSubmissionGuard;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class DuplicateSubmissionGuardTest extends TestCase
{
    private const FORM = ['subject' => 'Printer is jammed', 'description' => 'It jams on every page.', 'category_id' => '3'];

    public function test_the_same_content_gives_the_same_fingerprint(): void
    {
        $this->assertSame(
            DuplicateSubmissionGuard::fingerprint('client', 7, $this->request(self::FORM)),
            DuplicateSubmissionGuard::fingerprint('client', 7, $this->request(self::FORM)),
        );
    }

    public function test_extra_spaces_and_the_order_of_fields_do_not_matter(): void
    {
        $messy = [
            'category_id' => '3',
            'description' => "  It jams   on every\n page.  ",
            'subject' => "Printer  is jammed\t",
            '_token' => 'abc',
        ];

        $this->assertSame(
            DuplicateSubmissionGuard::fingerprint('client', 7, $this->request(self::FORM)),
            DuplicateSubmissionGuard::fingerprint('client', 7, $this->request($messy)),
        );
    }

    public function test_any_other_difference_gives_a_different_fingerprint(): void
    {
        $base = DuplicateSubmissionGuard::fingerprint('client', 7, $this->request(self::FORM));

        $this->assertNotSame($base, DuplicateSubmissionGuard::fingerprint('client', 8, $this->request(self::FORM)), 'another person');
        $this->assertNotSame($base, DuplicateSubmissionGuard::fingerprint('staff', 7, $this->request(self::FORM)), 'another form');
        $this->assertNotSame($base, DuplicateSubmissionGuard::fingerprint('client', 7, $this->request(['subject' => 'Printer is jammed again'] + self::FORM)), 'another subject');
        $this->assertNotSame($base, DuplicateSubmissionGuard::fingerprint('client', 7, $this->request(['category_id' => '4'] + self::FORM)), 'another category');
        $this->assertNotSame($base, DuplicateSubmissionGuard::fingerprint('client', 7, $this->request(self::FORM + ['province' => 'NCR'])), 'an extra field');
        $this->assertNotSame($base, DuplicateSubmissionGuard::fingerprint('client', 7, $this->request(['description' => 'It jams on every Page.'] + self::FORM)), 'capital letters count');
    }

    public function test_lists_in_the_form_count(): void
    {
        $this->assertNotSame(
            DuplicateSubmissionGuard::fingerprint('staff', 1, $this->request(self::FORM + ['assigned_to' => ['4']])),
            DuplicateSubmissionGuard::fingerprint('staff', 1, $this->request(self::FORM + ['assigned_to' => ['4', '5']])),
        );
    }

    public function test_attachments_count_by_name_and_size_in_any_order(): void
    {
        $one = $this->request(self::FORM, [UploadedFile::fake()->create('photo.pdf', 10), UploadedFile::fake()->create('notes.pdf', 20)]);
        $sameFilesOtherOrder = $this->request(self::FORM, [UploadedFile::fake()->create('notes.pdf', 20), UploadedFile::fake()->create('photo.pdf', 10)]);
        $otherName = $this->request(self::FORM, [UploadedFile::fake()->create('photo.pdf', 10), UploadedFile::fake()->create('other.pdf', 20)]);
        $otherSize = $this->request(self::FORM, [UploadedFile::fake()->create('photo.pdf', 10), UploadedFile::fake()->create('notes.pdf', 21)]);
        $none = $this->request(self::FORM);

        $fingerprint = fn (Request $request): string => DuplicateSubmissionGuard::fingerprint('client', 7, $request);

        $this->assertSame($fingerprint($one), $fingerprint($sameFilesOtherOrder));
        $this->assertNotSame($fingerprint($one), $fingerprint($otherName));
        $this->assertNotSame($fingerprint($one), $fingerprint($otherSize));
        $this->assertNotSame($fingerprint($one), $fingerprint($none));
    }

    /**
     * @param  array<string, mixed>  $fields
     * @param  list<UploadedFile>  $files
     */
    private function request(array $fields, array $files = []): Request
    {
        return Request::create('/client/tickets', 'POST', $fields, [], $files === [] ? [] : ['attachments' => $files]);
    }
}
