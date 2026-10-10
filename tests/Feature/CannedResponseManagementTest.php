<?php

namespace Tests\Feature;

use App\Models\CannedResponse;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

class CannedResponseManagementTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    public function test_admin_can_create_update_and_delete_a_canned_response(): void
    {
        $admin = $this->createUser('Canned Admin', 'canned-admin@example.com', User::ROLE_ADMIN);

        $storeResponse = $this->actingAs($admin)->post(route('admin.canned-responses.store'), [
            'title' => 'Greeting',
            'body' => 'Thank you for contacting support.',
        ]);
        $storeResponse->assertRedirect(route('admin.canned-responses.index'));

        $cannedResponse = CannedResponse::query()->where('title', 'Greeting')->firstOrFail();

        $updateResponse = $this->actingAs($admin)->put(route('admin.canned-responses.update', $cannedResponse), [
            'title' => 'Greeting Updated',
            'body' => 'Thanks so much for reaching out.',
        ]);
        $updateResponse->assertRedirect(route('admin.canned-responses.index'));
        $this->assertSame('Greeting Updated', $cannedResponse->fresh()->title);

        $destroyResponse = $this->actingAs($admin)->delete(route('admin.canned-responses.destroy', $cannedResponse));
        $destroyResponse->assertRedirect(route('admin.canned-responses.index'));
        $this->assertDatabaseMissing('canned_responses', ['id' => $cannedResponse->id]);
    }

    public function test_deleting_a_canned_response_asks_for_confirmation_in_the_shared_dialog(): void
    {
        $admin = $this->createUser('Canned Admin', 'canned-admin@example.com', User::ROLE_ADMIN);
        $response = CannedResponse::create(['title' => 'Greeting', 'body' => 'Hello.', 'created_by_user_id' => $admin->id]);

        $html = $this->actingAs($admin)->get(route('admin.canned-responses.index'))->assertOk()->getContent();

        // A trash icon opens the dialog; the row itself holds no form that could delete without asking.
        $this->assertStringContainsString('data-delete-action="'.route('admin.canned-responses.destroy', $response).'"', $html);
        $this->assertStringContainsString('aria-label="Delete Canned Response: Greeting"', $html);
        $this->assertStringContainsString('id="delete-confirm-modal"', $html);
        $this->assertSame(1, substr_count($html, 'name="_method" value="DELETE"'));
    }

    public function test_technician_cannot_manage_canned_responses_but_can_list_them(): void
    {
        $technician = $this->createAssignedSupportUser('Canned Technician', 'canned-technician@example.com');
        CannedResponse::create([
            'created_by_user_id' => $technician->id,
            'title' => 'Listable Response',
            'body' => 'Body text here.',
        ]);

        $manageResponse = $this->actingAs($technician)->get(route('admin.canned-responses.index'));
        $manageResponse->assertForbidden();

        $listResponse = $this->actingAs($technician)->getJson(route('admin.canned-responses.list'));
        $listResponse->assertOk();
        $listResponse->assertJsonFragment(['title' => 'Listable Response']);
    }

    public function test_search_filters_by_title_or_body(): void
    {
        $admin = $this->createUser('Search Admin', 'search-admin@example.com', User::ROLE_ADMIN);
        CannedResponse::create(['created_by_user_id' => $admin->id, 'title' => 'Password Reset', 'body' => 'Here are the steps.']);
        CannedResponse::create(['created_by_user_id' => $admin->id, 'title' => 'Network Outage', 'body' => 'We are investigating.']);

        $response = $this->actingAs($admin)->get(route('admin.canned-responses.index', ['search' => 'password']));

        $response->assertOk();
        $response->assertSeeText('Password Reset');
        $response->assertDontSeeText('Network Outage');
    }

    public function test_feature_flag_disables_all_canned_response_routes(): void
    {
        config(['helpdesk.features.canned_replies' => false]);

        $admin = $this->createUser('Disabled Admin', 'disabled-admin@example.com', User::ROLE_ADMIN);
        $cannedResponse = CannedResponse::create([
            'created_by_user_id' => $admin->id,
            'title' => 'Still In Database',
            'body' => 'Body text.',
        ]);

        $this->actingAs($admin)->get(route('admin.canned-responses.index'))->assertNotFound();
        $this->actingAs($admin)->getJson(route('admin.canned-responses.list'))->assertNotFound();
        $this->actingAs($admin)->post(route('admin.canned-responses.store'), [
            'title' => 'Blocked',
            'body' => 'Blocked body',
        ])->assertNotFound();
        $this->actingAs($admin)->delete(route('admin.canned-responses.destroy', $cannedResponse))->assertNotFound();
    }

    public function test_feature_flag_hides_the_navigation_link_and_reply_form_picker(): void
    {
        config(['legal.require_acceptance' => false]);
        config(['helpdesk.features.canned_replies' => false]);

        $admin = $this->createUser('Nav Admin', 'nav-admin@example.com', User::ROLE_ADMIN);

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertDontSee('Canned Responses');
    }
}
