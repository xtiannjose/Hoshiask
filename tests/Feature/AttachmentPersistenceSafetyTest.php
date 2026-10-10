<?php

namespace Tests\Feature;

use App\Models\Attachment;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class AttachmentPersistenceSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_ticket_creation_rolls_back_when_attachment_record_persistence_fails(): void
    {
        config(['legal.require_acceptance' => false]);

        $client = User::create([
            'name' => 'Attachment Safety Client',
            'email' => 'attachment-safety-client@example.com',
            'phone' => '09189990011',
            'department' => 'Hoshishi',
            'role' => User::ROLE_CLIENT,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $category = Category::create([
            'name' => 'Attachment Safety',
            'description' => 'Attachment safety category',
            'color' => '#0f8d88',
            'is_active' => true,
        ]);

        Event::listen('eloquent.creating: '.Attachment::class, static function (): void {
            throw new RuntimeException('Simulated attachment create failure.');
        });

        $this->withoutExceptionHandling();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Simulated attachment create failure.');

        try {
            $this->actingAs($client)->post(route('client.tickets.store'), [
                'name' => 'Attachment Safety Client',
                'contact_number' => '09189990011',
                'email' => 'attachment-safety-client@example.com',
                'province' => 'metro manila',
                'municipality' => 'pasig city',
                'subject' => 'rollback test',
                'description' => 'This ticket should roll back fully.',
                'category_id' => $category->id,
                'priority' => 'high',
                'ticket_consent' => '1',
                'attachments' => [UploadedFile::fake()->create('proof.txt', 8, 'text/plain')],
            ]);
        } finally {
            $this->assertDatabaseCount('tickets', 0);
            $this->assertDatabaseCount('attachments', 0);
            $this->assertSame([], Storage::disk('attachments-testing')->allFiles('attachments'));
        }
    }
}
