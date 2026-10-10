<?php

namespace Tests\Feature;

use App\Models\Ticket;
use App\Models\User;
use App\Support\PhilippineLocations;
use App\Support\UploadLimits;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

class QaFormsAndUploadsTest extends TestCase
{
    use CreatesTestData, RefreshDatabase;

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function ticketPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Form Client',
            'contact_number' => '09181234567',
            'email' => 'form-client@example.com',
            'province' => 'NCR',
            'municipality' => 'Pasig',
            'subject' => 'Printer jam',
            'description' => 'Paper is stuck.',
            'category_id' => $this->createCategory()->id,
            'ticket_consent' => '1',
            'attachments' => [UploadedFile::fake()->create('proof.txt', 8, 'text/plain')],
        ], $overrides);
    }

    private function submit(User $client, array $payload)
    {
        return $this->actingAs($client)->from(route('client.tickets.create'))
            ->post(route('client.tickets.store'), $payload);
    }

    // ---- Q-08 readable upload errors ------------------------------------------

    public function test_an_unaccepted_file_type_error_names_the_file_and_the_allowed_types(): void
    {
        $client = $this->createClient();

        $response = $this->submit($client, $this->ticketPayload([
            'attachments' => [UploadedFile::fake()->create('photo.heic', 20, 'image/heic')],
        ]));

        $message = session('errors')->first('attachments.0');
        $this->assertStringContainsString('"photo.heic" is not an accepted file type', $message);
        $this->assertStringContainsString('JPG, PNG, PDF', $message);
        $this->assertStringNotContainsString('attachments.0', $message);
        $response->assertSessionHasErrors('attachments.0');
    }

    public function test_an_oversized_file_error_names_the_file_and_states_the_limit(): void
    {
        $client = $this->createClient();

        $this->submit($client, $this->ticketPayload([
            'attachments' => [UploadedFile::fake()->create('scan.pdf', 11 * 1024, 'application/pdf')],
        ]));

        $message = session('errors')->first('attachments.0');
        $this->assertStringContainsString('"scan.pdf" is too large', $message);
        $this->assertStringContainsString(UploadLimits::perFileLabel(), $message);
    }

    public function test_a_file_the_server_could_not_receive_is_explained_not_reported_as_failed_to_upload(): void
    {
        $client = $this->createClient();
        $path = tempnam(sys_get_temp_dir(), 'qa');
        $broken = new UploadedFile($path, 'big-photo.jpg', 'image/jpeg', UPLOAD_ERR_INI_SIZE, true);

        $this->submit($client, $this->ticketPayload(['attachments' => [$broken]]));

        $message = session('errors')->first('attachments.0');
        $this->assertStringContainsString('"big-photo.jpg"', $message);
        $this->assertStringContainsString('could not receive', $message);
        $this->assertStringNotContainsString('failed to upload', $message);
        @unlink($path);
    }

    public function test_the_second_file_is_named_correctly_when_only_it_is_bad(): void
    {
        $client = $this->createClient();

        $this->submit($client, $this->ticketPayload([
            'attachments' => [
                UploadedFile::fake()->create('fine.txt', 4, 'text/plain'),
                UploadedFile::fake()->create('notes.exe', 4, 'application/octet-stream'),
            ],
        ]));

        $errors = session('errors');
        $this->assertFalse($errors->has('attachments.0'));
        $this->assertStringContainsString('"notes.exe" is not an accepted file type', $errors->first('attachments.1'));
    }

    public function test_a_reply_with_nothing_in_it_says_what_is_missing(): void
    {
        $client = $this->createClient();
        $ticket = $this->createTicket($client, $this->createCategory(), ['status' => 'open']);

        $this->actingAs($client)->postJson(route('client.tickets.reply', $ticket), ['message' => '   '])
            ->assertUnprocessable()
            ->assertJsonPath('errors.message.0', 'Type a message or attach a file first.');
    }

    // ---- Q-09 phone numbers ------------------------------------------------------

    public function test_a_ticket_with_a_letters_only_contact_number_is_refused(): void
    {
        $client = $this->createClient();

        $this->submit($client, $this->ticketPayload(['contact_number' => 'abc']))
            ->assertSessionHasErrors('contact_number');

        $this->assertSame(0, Ticket::query()->count());
    }

    public function test_common_philippine_number_formats_are_accepted(): void
    {
        $client = $this->createClient();

        foreach (['09181234567', '+63 918 123 4567', '(02) 8123-4567'] as $number) {
            $this->submit($client, $this->ticketPayload(['contact_number' => $number, 'subject' => 'Phone '.$number]))
                ->assertSessionDoesntHaveErrors('contact_number');
        }
    }

    public function test_creating_a_user_with_a_letters_only_phone_is_refused(): void
    {
        $admin = $this->createUser('Admin', 'admin-phone@example.com', User::ROLE_ADMIN);

        $this->actingAs($admin)->from(route('admin.users.create'))->post(route('admin.users.store'), [
            'username' => 'phone.tester',
            'name' => 'Phone Tester',
            'phone' => 'abc',
            'department' => 'Hoshishi',
            'role' => User::ROLE_CLIENT,
            'password' => 'SomePass1234',
            'password_confirmation' => 'SomePass1234',
        ])->assertSessionHasErrors('phone');

        $this->assertDatabaseMissing('users', ['username' => 'phone.tester']);
    }

    public function test_phone_fields_use_the_telephone_keypad(): void
    {
        $client = $this->createClient();
        $admin = $this->createUser('Admin', 'admin-tel@example.com', User::ROLE_ADMIN);

        $this->actingAs($client)->get(route('client.tickets.create'))
            ->assertSee('type="tel"', false)
            ->assertSee('inputmode="tel"', false)
            ->assertSee('autocomplete="tel"', false);

        $this->actingAs($admin)->get(route('admin.users.create'))
            ->assertSee('type="tel" name="phone"', false);
    }

    // ---- Q-11 prefill ---------------------------------------------------------------

    public function test_the_ticket_form_starts_with_the_clients_own_details(): void
    {
        $client = $this->createClient('Maria Santos', 'maria@example.com', ['phone' => '09175550000']);

        $this->actingAs($client)->get(route('client.tickets.create'))
            ->assertOk()
            ->assertSee('value="Maria Santos"', false)
            ->assertSee('value="09175550000"', false)
            ->assertSee('value="maria@example.com"', false)
            ->assertSee('autocomplete="name"', false)
            ->assertSee('autocomplete="email"', false);
    }

    public function test_typed_values_win_over_the_prefill_after_a_failed_submit(): void
    {
        $client = $this->createClient('Maria Santos', 'maria@example.com', ['phone' => '09175550000']);

        $this->actingAs($client)->withSession(['_old_input' => ['name' => 'Someone Else', 'contact_number' => '09990001111']])
            ->get(route('client.tickets.create'))
            ->assertSee('value="Someone Else"', false)
            ->assertSee('value="09990001111"', false);
    }

    // ---- Q-07 error summary --------------------------------------------------------

    public function test_a_failed_submit_shows_a_summary_of_problems_with_links_to_each_field(): void
    {
        $client = $this->createClient();

        $response = $this->submit($client, $this->ticketPayload([
            'ticket_consent' => null,
            'contact_number' => 'abc',
        ]));
        $response->assertRedirect(route('client.tickets.create'));

        $page = $this->followRedirects($response);
        $page->assertSee('data-error-summary', false)
            ->assertSee('There are 2 problems to fix before this can be sent.')
            ->assertSee('href="#contact_number"', false)
            ->assertSee('href="#ticket_consent"', false)
            ->assertSee('id="ticket_consent"', false)
            ->assertSee('Your file choice was not kept');
    }

    public function test_a_clean_page_has_no_error_summary(): void
    {
        $client = $this->createClient();

        $this->actingAs($client)->get(route('client.tickets.create'))
            ->assertOk()
            ->assertDontSee('data-error-summary', false);
    }

    // ---- Q-02 upload limits shown truthfully ----------------------------------------

    public function test_the_forms_state_and_expose_the_real_upload_limits(): void
    {
        $client = $this->createClient();
        $label = UploadLimits::perFileLabel();

        $this->actingAs($client)->get(route('client.tickets.create'))
            ->assertSee('max '.$label.' each', false)
            ->assertSee('data-max-file-bytes="'.UploadLimits::perFileBytes().'"', false)
            ->assertSee('data-max-request-bytes="'.UploadLimits::perRequestBytes().'"', false)
            ->assertSee('data-upload-guard="attachments-guard"', false);
    }

    public function test_the_reply_box_explains_enter_to_send(): void
    {
        $client = $this->createClient();
        $ticket = $this->createTicket($client, $this->createCategory(), ['status' => 'open']);

        $this->actingAs($client)->get(route('client.tickets.show', $ticket))
            ->assertSee('Press Enter to send, or Shift+Enter for a new line.');
    }

    // ---- Q-10 province and city suggestions ---------------------------------------------

    public function test_the_municipality_endpoint_lists_the_cities_of_a_province(): void
    {
        $client = $this->createClient();

        $this->actingAs($client)->getJson(route('locations.municipalities', ['province' => 'Iloilo']))
            ->assertOk()
            ->assertJsonPath('municipalities.0', 'Ajuy')
            ->assertJsonMissing(['Pasig']);

        $this->actingAs($client)->getJson(route('locations.municipalities', ['province' => 'metro manila']))
            ->assertOk()
            ->assertJsonFragment(['municipalities' => PhilippineLocations::municipalitiesFor('NCR')]);
    }

    public function test_the_municipality_endpoint_returns_an_empty_list_for_unknown_or_blank_provinces(): void
    {
        $client = $this->createClient();

        $this->actingAs($client)->getJson(route('locations.municipalities', ['province' => 'Atlantis']))
            ->assertOk()->assertExactJson(['municipalities' => []]);
        $this->actingAs($client)->getJson(route('locations.municipalities'))
            ->assertOk()->assertExactJson(['municipalities' => []]);
    }

    public function test_the_municipality_endpoint_needs_a_signed_in_user(): void
    {
        $this->getJson(route('locations.municipalities', ['province' => 'Iloilo']))->assertUnauthorized();
    }

    public function test_a_city_lookup_between_a_refused_form_and_its_page_keeps_the_errors_and_typed_values(): void
    {
        $client = $this->createClient();
        $this->submit($client, $this->ticketPayload(['subject' => 'Kept subject', 'contact_number' => 'call me']))
            ->assertRedirect(route('client.tickets.create'));

        // The province box loses focus on the same click that sent the form.
        $this->actingAs($client)->getJson(route('locations.municipalities', ['province' => 'Iloilo']))->assertOk();

        $this->actingAs($client)->get(route('client.tickets.create'))
            ->assertOk()
            ->assertSee('Kept subject')
            ->assertSee('call me');
    }

    public function test_a_city_lookup_before_the_new_ticket_page_keeps_the_created_notice(): void
    {
        $client = $this->createClient();
        $this->submit($client, $this->ticketPayload())->assertSessionHasNoErrors();
        $ticket = Ticket::query()->firstOrFail();

        $this->actingAs($client)->getJson(route('locations.municipalities', ['province' => 'Iloilo']))->assertOk();

        $this->actingAs($client)->get(route('client.tickets.show', $ticket))->assertSee('Ticket created successfully!');
    }

    public function test_both_ticket_forms_offer_province_and_city_suggestions(): void
    {
        $client = $this->createClient();
        $admin = $this->createUser('Admin', 'admin-locations@example.com', User::ROLE_ADMIN);

        foreach ([[$client, route('client.tickets.create')], [$admin, route('admin.tickets.create')]] as [$user, $url]) {
            $this->actingAs($user)->get($url)
                ->assertOk()
                ->assertSee('<datalist id="province-options">', false)
                ->assertSee('<option value="Iloilo"></option>', false)
                ->assertSee('<datalist id="municipality-options">', false)
                ->assertSee('list="province-options"', false)
                ->assertSee('data-location-municipalities-url="'.route('locations.municipalities').'"', false);
        }
    }

    public function test_an_unrecognized_province_or_city_error_names_what_was_typed_and_what_to_do(): void
    {
        $client = $this->createClient();

        $this->submit($client, $this->ticketPayload(['province' => 'Iloylo']));
        $this->assertSame(
            '"Iloylo" is not a recognized province. Start typing and pick one from the list (use NCR for Metro Manila).',
            session('errors')->first('province')
        );

        $this->submit($client, $this->ticketPayload(['province' => 'Iloilo', 'municipality' => 'Pasig']));
        $this->assertSame(
            '"Pasig" is not a recognized city or municipality in Iloilo. Start typing and pick one from the list.',
            session('errors')->first('municipality')
        );
    }
}
