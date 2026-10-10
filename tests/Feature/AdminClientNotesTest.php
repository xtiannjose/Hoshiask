<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminClientNotesTest extends TestCase
{
    use RefreshDatabase;

    public function test_shadow_can_set_client_notes_during_client_creation(): void
    {
        $shadow = User::create([
            'name' => 'Client Notes Shadow Creator',
            'email' => 'client-notes-shadow-creator@example.com',
            'phone' => '09150000000',
            'department' => 'Hoshishi',
            'role' => User::ROLE_SHADOW,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $response = $this->actingAs($shadow)->post(route('admin.users.store'), [
            'name' => 'Created Client Notes',
            'email' => 'created-client-notes@example.com',
            'phone' => '09159999991',
            'department' => 'Hoshishi',
            'role' => User::ROLE_CLIENT,
            'client_notes' => 'Create-time note for onboarding reminders.',
            'password' => 'purple-giraffe-lamp',
            'password_confirmation' => 'purple-giraffe-lamp',
        ]);

        $response->assertRedirect(route('admin.users.clients', [
            'search' => 'created.client.notes',
        ]));

        $createdClient = User::where('email', 'created-client-notes@example.com')->firstOrFail();
        $this->assertSame('Create-time note for onboarding reminders.', $createdClient->client_notes);
    }

    public function test_non_shadow_client_create_rejects_client_notes_payload(): void
    {
        $admin = User::create([
            'name' => 'Client Notes Admin Create Guard',
            'email' => 'client-notes-admin-create-guard@example.com',
            'phone' => '09150000007',
            'department' => 'Hoshishi',
            'role' => User::ROLE_ADMIN,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'Client Create Guard',
            'email' => 'client-create-guard@example.com',
            'phone' => '09159999995',
            'department' => 'Hoshishi',
            'role' => User::ROLE_CLIENT,
            'client_notes' => 'This must be rejected for non-shadow create.',
            'password' => 'purple-giraffe-lamp',
            'password_confirmation' => 'purple-giraffe-lamp',
        ]);

        $response->assertSessionHasErrors('client_notes');
        $this->assertDatabaseMissing('users', [
            'email' => 'client-create-guard@example.com',
        ]);
    }

    public function test_non_client_create_rejects_client_notes_payload_even_for_shadow(): void
    {
        $shadow = User::create([
            'name' => 'Client Notes Create Guard',
            'email' => 'client-notes-create-guard@example.com',
            'phone' => '09150000007',
            'department' => 'Hoshishi',
            'role' => User::ROLE_SHADOW,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $response = $this->actingAs($shadow)->post(route('admin.users.store'), [
            'name' => 'Technical Create Guard',
            'email' => 'technical-create-guard@example.com',
            'phone' => '09159999992',
            'department' => 'Hoshishi',
            'role' => User::ROLE_TECHNICAL,
            'client_notes' => 'This must be rejected.',
            'password' => 'purple-giraffe-lamp',
            'password_confirmation' => 'purple-giraffe-lamp',
        ]);

        $response->assertSessionHasErrors('client_notes');
        $this->assertDatabaseMissing('users', [
            'email' => 'technical-create-guard@example.com',
        ]);
    }

    public function test_shadow_can_update_client_notes_from_edit_user(): void
    {
        $shadow = User::create([
            'name' => 'Client Notes Shadow Editor',
            'email' => 'client-notes-shadow-editor@example.com',
            'phone' => '09150000001',
            'department' => 'Hoshishi',
            'role' => User::ROLE_SHADOW,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $client = User::create([
            'name' => 'Client Notes Target',
            'email' => 'client-notes-target@example.com',
            'phone' => '09150000002',
            'department' => 'Hoshishi',
            'role' => User::ROLE_CLIENT,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $response = $this->actingAs($shadow)->put(route('admin.users.update', $client), [
            'name' => 'Client Notes Target',
            'email' => 'client-notes-target@example.com',
            'phone' => '09150000002',
            'department' => 'Hoshishi',
            'role' => User::ROLE_CLIENT,
            'is_active' => true,
            'client_notes' => 'Client prefers SMS follow-up for urgent issues.',
        ]);

        $response->assertRedirect(route('admin.users.index'));

        $client->refresh();
        $this->assertSame('Client prefers SMS follow-up for urgent issues.', $client->client_notes);
    }

    public function test_client_notes_render_in_user_information_section_for_shadow_only(): void
    {
        $shadow = User::create([
            'name' => 'Client Notes Shadow Viewer',
            'email' => 'client-notes-shadow-viewer@example.com',
            'phone' => '09150000003',
            'department' => 'Hoshishi',
            'role' => User::ROLE_SHADOW,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $client = User::create([
            'name' => 'Client Notes Visible',
            'email' => 'client-notes-visible@example.com',
            'phone' => '09150000004',
            'department' => 'Hoshishi',
            'role' => User::ROLE_CLIENT,
            'password' => Hash::make('password123'),
            'client_notes' => 'Escalate network outages immediately.',
            'is_active' => true,
        ]);

        $response = $this->actingAs($shadow)->get(route('admin.users.show', $client));

        $response->assertOk();
        $response->assertSee('Client Notes');
        $response->assertSee('Escalate network outages immediately.');
    }

    public function test_non_shadow_user_cannot_update_client_notes_and_existing_value_is_preserved(): void
    {
        $admin = User::create([
            'name' => 'Client Notes Non Shadow Editor',
            'email' => 'client-notes-non-shadow-editor@example.com',
            'phone' => '09150000031',
            'department' => 'Hoshishi',
            'role' => User::ROLE_ADMIN,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $client = User::create([
            'name' => 'Client Notes Existing',
            'email' => 'client-notes-existing@example.com',
            'phone' => '09150000032',
            'department' => 'Hoshishi',
            'role' => User::ROLE_CLIENT,
            'password' => Hash::make('password123'),
            'client_notes' => 'Existing shadow note should remain.',
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)
            ->from(route('admin.users.edit', $client))
            ->put(route('admin.users.update', $client), [
                'name' => 'Client Notes Existing',
                'email' => 'client-notes-existing@example.com',
                'phone' => '09150000032',
                'department' => 'Hoshishi',
                'role' => User::ROLE_CLIENT,
                'is_active' => true,
                'client_notes' => 'This should be rejected for non-shadow update.',
            ]);

        $response->assertRedirect(route('admin.users.edit', $client));
        $response->assertSessionHasErrors('client_notes');

        $client->refresh();
        $this->assertSame('Existing shadow note should remain.', $client->client_notes);
    }

    public function test_non_shadow_user_cannot_see_client_notes_in_user_details(): void
    {
        $admin = User::create([
            'name' => 'Client Notes Non Shadow Viewer',
            'email' => 'client-notes-non-shadow-viewer@example.com',
            'phone' => '09150000033',
            'department' => 'Hoshishi',
            'role' => User::ROLE_ADMIN,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $client = User::create([
            'name' => 'Client Notes Hidden',
            'email' => 'client-notes-hidden@example.com',
            'phone' => '09150000034',
            'department' => 'Hoshishi',
            'role' => User::ROLE_CLIENT,
            'password' => Hash::make('password123'),
            'client_notes' => 'Should be hidden from non-shadow viewers.',
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.users.show', $client));
        $response->assertOk();
        $response->assertDontSee('>Client Notes<', false);
        $response->assertDontSee('Should be hidden from non-shadow viewers.');
    }

    public function test_non_client_user_update_rejects_client_notes_payload(): void
    {
        $shadow = User::create([
            'name' => 'Client Notes Shadow',
            'email' => 'client-notes-shadow@example.com',
            'phone' => '09150000005',
            'department' => 'Hoshishi',
            'role' => User::ROLE_SHADOW,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $technical = User::create([
            'name' => 'Client Notes Technical',
            'email' => 'client-notes-technical@example.com',
            'phone' => '09150000006',
            'department' => 'Hoshishi',
            'role' => User::ROLE_TECHNICAL,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $response = $this->actingAs($shadow)
            ->from(route('admin.users.edit', $technical))
            ->put(route('admin.users.update', $technical), [
                'name' => 'Client Notes Technical',
                'email' => 'client-notes-technical@example.com',
                'phone' => '09150000006',
                'department' => 'Hoshishi',
                'role' => User::ROLE_TECHNICAL,
                'is_active' => true,
                'client_notes' => 'This should be rejected.',
            ]);

        $response->assertRedirect(route('admin.users.edit', $technical));
        $response->assertSessionHasErrors('client_notes');

        $technical->refresh();
        $this->assertNull($technical->client_notes);
    }
}
