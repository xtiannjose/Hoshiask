<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthLoginValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_shows_error_for_invalid_credentials(): void
    {
        $response = $this->from(route('login'))->post('/login', [
            'login' => 'not-an-email',
            'password' => 'password123',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('login');
    }

    public function test_user_cannot_login_with_display_name_only(): void
    {
        $user = User::create([
            'name' => 'Juan Dela Cruz',
            'email' => 'juan-login@example.com',
            'phone' => '09120001111',
            'department' => 'Hoshishi',
            'role' => User::ROLE_CLIENT,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $response = $this->from(route('login'))->post(route('login'), [
            'login' => 'Juan Dela Cruz',
            'password' => 'password123',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('login');
        $this->assertGuest();
    }

    public function test_user_can_login_with_email_case_insensitively(): void
    {
        $user = User::create([
            'name' => 'Juan Dela Cruz',
            'email' => 'juan-case-sensitive@example.com',
            'phone' => '09123334444',
            'department' => 'Hoshishi',
            'role' => User::ROLE_CLIENT,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $response = $this->post(route('login'), [
            'login' => 'JUAN-CASE-SENSITIVE@EXAMPLE.COM',
            'password' => 'password123',
        ]);

        $response->assertRedirect('/client/dashboard');
        $this->assertAuthenticatedAs($user);
    }

    public function test_account_lockout_blocks_login_after_repeated_failed_attempts(): void
    {
        User::create([
            'name' => 'Lockout User',
            'email' => 'lockout-user@example.com',
            'phone' => '09125556666',
            'department' => 'Hoshishi',
            'role' => User::ROLE_CLIENT,
            'password' => Hash::make('StrongPass123!'),
            'is_active' => true,
        ]);

        // The lock is kept per login and per address: eight wrong passwords from one address lock that address out.
        foreach (range(1, 8) as $attempt) {
            $response = $this->from(route('login'))->post(route('login'), [
                'login' => 'lockout-user@example.com',
                'password' => 'invalid-password',
            ], [
                'REMOTE_ADDR' => '198.51.100.20',
            ]);

            $response->assertRedirect(route('login'));
            $response->assertSessionHasErrors('login');
        }

        $lockedResponse = $this->from(route('login'))->post(route('login'), [
            'login' => 'lockout-user@example.com',
            'password' => 'StrongPass123!',
        ], [
            'REMOTE_ADDR' => '198.51.100.20',
        ]);

        $lockedResponse->assertRedirect(route('login'));
        $lockedResponse->assertSessionHasErrors('login');
        $this->assertGuest();

        // Another address is not locked: see LoginLockoutPerSourceTest.
        $this->from(route('login'))->post(route('login'), [
            'login' => 'lockout-user@example.com',
            'password' => 'StrongPass123!',
        ], [
            'REMOTE_ADDR' => '203.0.113.250',
        ])->assertSessionHasNoErrors();
        $this->assertAuthenticated();
    }

    public function test_user_can_login_with_generated_username(): void
    {
        $user = User::create([
            'name' => 'Unique Username User',
            'email' => 'unique-username@example.com',
            'phone' => '09127778888',
            'department' => 'Hoshishi',
            'role' => User::ROLE_CLIENT,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $response = $this->post(route('login'), [
            'login' => (string) $user->username,
            'password' => 'password123',
        ]);

        $response->assertRedirect('/client/dashboard');
        $this->assertAuthenticatedAs($user);
    }

    public function test_duplicate_display_names_do_not_block_username_login(): void
    {
        User::create([
            'name' => 'Same Login Name',
            'email' => 'same-login-1@example.com',
            'username' => 'same.login.one',
            'phone' => '09128889990',
            'department' => 'Hoshishi',
            'role' => User::ROLE_CLIENT,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $targetUser = User::create([
            'name' => 'Same Login Name',
            'email' => 'same-login-2@example.com',
            'username' => 'same.login.two',
            'phone' => '09128889991',
            'department' => 'Hoshishi',
            'role' => User::ROLE_CLIENT,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $response = $this->post(route('login'), [
            'login' => 'same.login.two',
            'password' => 'password123',
        ]);

        $response->assertRedirect('/client/dashboard');
        $this->assertAuthenticatedAs($targetUser);
    }

    public function test_client_login_ignores_admin_intended_url_and_redirects_to_client_dashboard(): void
    {
        $user = User::create([
            'name' => 'Client Redirect User',
            'email' => 'client-redirect@example.com',
            'phone' => '09129990000',
            'department' => 'Hoshishi',
            'role' => User::ROLE_CLIENT,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $this->get('/admin/dashboard')->assertRedirect(route('login'));

        $response = $this->post(route('login'), [
            'login' => 'client-redirect@example.com',
            'password' => 'password123',
        ]);

        $response->assertRedirect('/client/dashboard');
        $this->assertAuthenticatedAs($user);
    }
}
