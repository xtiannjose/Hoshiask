<?php

namespace Tests\Feature;

use App\Http\Middleware\TrackLastSeen;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LastSeenTrackingTest extends TestCase
{
    use RefreshDatabase;

    public function test_signed_in_requests_record_last_seen_at_at_most_once_a_minute(): void
    {
        $this->freezeSecond();

        $technical = User::create([
            'name' => 'Tracked Technician',
            'email' => 'tracked-technician@example.com',
            'phone' => '09123459001',
            'department' => 'Hoshishi',
            'role' => User::ROLE_TECHNICAL,
            'password' => Hash::make('TechPass123!'),
            'is_active' => true,
        ]);
        $originalUpdatedAt = $technical->updated_at;
        $firstVisitAt = now();

        $this->actingAs($technical)->get(route('admin.dashboard'))->assertOk();
        $this->assertTrue($technical->fresh()->last_seen_at->equalTo($firstVisitAt));

        $this->travel(TrackLastSeen::WRITE_INTERVAL_SECONDS - 10)->seconds();
        $this->actingAs($technical->fresh())->get(route('admin.dashboard'))->assertOk();
        $this->assertTrue($technical->fresh()->last_seen_at->equalTo($firstVisitAt));

        $this->travel(20)->seconds();
        $this->actingAs($technical->fresh())->get(route('admin.dashboard'))->assertOk();

        $technical->refresh();
        $this->assertTrue($technical->last_seen_at->equalTo(now()));
        $this->assertTrue($technical->updated_at->equalTo($originalUpdatedAt));
    }

    public function test_guest_requests_do_not_record_last_seen(): void
    {
        $client = User::create([
            'name' => 'Untracked Client',
            'email' => 'untracked-client@example.com',
            'phone' => '09123459002',
            'department' => 'Hoshishi',
            'role' => User::ROLE_CLIENT,
            'password' => Hash::make('ClientPass123!'),
            'is_active' => true,
        ]);

        $this->get(route('login'))->assertOk();

        $this->assertNull($client->fresh()->last_seen_at);
    }
}
