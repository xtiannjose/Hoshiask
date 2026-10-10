<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\UploadLimits;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;
use ZipArchive;

class AttachmentLimitsTest extends TestCase
{
    use CreatesTestData;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['legal.require_acceptance' => false]);
    }

    /**
     * @param  array<int, UploadedFile>  $files
     * @return array<string, mixed>
     */
    private function ticketPayload(User $client, int $categoryId, array $files): array
    {
        return [
            'name' => $client->name,
            'contact_number' => '09189990011',
            'email' => $client->email,
            'province' => 'metro manila',
            'municipality' => 'pasig city',
            'subject' => 'attachment limit test',
            'description' => 'Checking how many files one ticket takes.',
            'category_id' => $categoryId,
            'priority' => 'high',
            'ticket_consent' => '1',
            'attachments' => $files,
        ];
    }

    /**
     * @return array<int, UploadedFile>
     */
    private function textFiles(int $count): array
    {
        return array_map(fn (int $n) => UploadedFile::fake()->create("note-{$n}.txt", 1, 'text/plain'), range(1, $count));
    }

    public function test_a_ticket_takes_ten_attachments_but_not_eleven(): void
    {
        $client = $this->createClient('Limit Client', 'limit-client@example.com');
        $category = $this->createCategory();

        $this->actingAs($client)
            ->post(route('client.tickets.store'), $this->ticketPayload($client, $category->id, $this->textFiles(UploadLimits::MAX_FILES + 1)))
            ->assertSessionHasErrors('attachments');
        $this->assertDatabaseCount('tickets', 0);

        $this->actingAs($client)
            ->post(route('client.tickets.store'), $this->ticketPayload($client, $category->id, $this->textFiles(UploadLimits::MAX_FILES)))
            ->assertSessionHasNoErrors();
        $this->assertDatabaseCount('tickets', 1);
        $this->assertSame(10, UploadLimits::MAX_FILES);
    }

    public function test_the_too_many_files_message_says_the_limit(): void
    {
        $client = $this->createClient('Message Client', 'message-client@example.com');
        $category = $this->createCategory();

        $response = $this->actingAs($client)
            ->post(route('client.tickets.store'), $this->ticketPayload($client, $category->id, $this->textFiles(11)));

        $this->assertStringContainsString('at most 10 files', session('errors')->first('attachments'));
        $response->assertSessionHasErrors('attachments');
    }

    public function test_a_reply_takes_at_most_ten_attachments(): void
    {
        $client = $this->createClient('Reply Limit Client', 'reply-limit-client@example.com');
        $ticket = $this->createTicket($client, $this->createCategory());

        $this->actingAs($client)
            ->postJson(route('client.tickets.reply', $ticket), ['message' => 'Here are my files', 'attachments' => $this->textFiles(11)])
            ->assertStatus(422)
            ->assertJsonValidationErrors('attachments');
    }

    public function test_real_word_and_excel_open_xml_files_are_accepted(): void
    {
        $client = $this->createClient('Office Client', 'office-client@example.com');
        $category = $this->createCategory();

        $files = [
            $this->openXmlFile('report.docx', 'word/document.xml', 'wordprocessingml.document.main+xml'),
            $this->openXmlFile('sheet.xlsx', 'xl/workbook.xml', 'spreadsheetml.sheet.main+xml'),
        ];

        $this->actingAs($client)
            ->post(route('client.tickets.store'), $this->ticketPayload($client, $category->id, $files))
            ->assertSessionHasNoErrors();
        $this->assertDatabaseCount('attachments', 2);
    }

    public function test_an_executable_renamed_to_pdf_is_refused(): void
    {
        $client = $this->createClient('Disguise Client', 'disguise-client@example.com');
        $category = $this->createCategory();
        $path = tempnam(sys_get_temp_dir(), 'exe');
        file_put_contents($path, "MZ\x90\x00\x03\x00\x00\x00 not really a pdf".str_repeat("\0", 64));
        $disguised = new UploadedFile($path, 'invoice.pdf', 'application/pdf', null, true);

        $this->actingAs($client)
            ->post(route('client.tickets.store'), $this->ticketPayload($client, $category->id, [$disguised]))
            ->assertSessionHasErrors('attachments.0');
        $this->assertDatabaseCount('tickets', 0);
    }

    public function test_a_bulk_action_on_too_many_tickets_is_refused_with_a_clear_message(): void
    {
        $admin = $this->createUser('Bulk Limit Admin', 'bulk-limit-admin@example.com', User::ROLE_ADMIN);

        $response = $this->actingAs($admin)->postJson(route('admin.tickets.bulk-action'), [
            'action' => 'status',
            'status' => 'in_progress',
            'selected_ids' => range(1, 201),
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('selected_ids');
        $this->assertStringContainsString('200', $response->json('errors.selected_ids.0'));
    }

    /**
     * A minimal but genuine Open XML package: libmagic recognises it by its entry names, as it does a real file.
     */
    private function openXmlFile(string $name, string $mainPart, string $contentTypeSuffix): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'ooxml');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="xml" ContentType="application/xml"/><Override PartName="/'.$mainPart.'" ContentType="application/vnd.openxmlformats-officedocument.'.$contentTypeSuffix.'"/></Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="'.$mainPart.'"/></Relationships>');
        $zip->addFromString($mainPart, '<?xml version="1.0" encoding="UTF-8"?><root/>');
        $zip->close();

        $mime = str_ends_with($name, '.docx')
            ? 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
            : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

        return new UploadedFile($path, $name, $mime, null, true);
    }
}
