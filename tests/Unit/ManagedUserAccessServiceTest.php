<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\Admin\ManagedUserAccessService;
use App\Services\Admin\UserDirectoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

class ManagedUserAccessServiceTest extends TestCase
{
    use CreatesTestData;
    use RefreshDatabase;

    private function service(): ManagedUserAccessService
    {
        return new ManagedUserAccessService(app(UserDirectoryService::class));
    }

    public function test_destroy_error_prevents_self_deletion(): void
    {
        $admin = $this->createUser('Admin', 'admin1@example.com', User::ROLE_ADMIN);

        $this->assertSame(
            'You cannot delete your own account.',
            $this->service()->destroyError($admin, $admin)
        );
    }

    public function test_destroy_error_prevents_deleting_locked_profile(): void
    {
        $admin = $this->createUser('Admin', 'admin2@example.com', User::ROLE_ADMIN);
        $client = $this->createClient('Locked Client', 'locked2@example.com', [
            'is_profile_locked' => true,
        ]);

        $this->assertSame(
            'Locked users cannot be deleted. Unlock the profile first.',
            $this->service()->destroyError($admin, $client)
        );
    }

    public function test_destroy_error_prevents_non_shadow_from_deleting_shadow_account(): void
    {
        $admin = $this->createUser('Admin', 'admin3@example.com', User::ROLE_ADMIN);
        $shadow = $this->createUser('Shadow', 'shadow3@example.com', User::ROLE_SHADOW);

        $this->assertSame(
            'You do not have permission to delete this user.',
            $this->service()->destroyError($admin, $shadow)
        );
    }

    public function test_destroy_error_prevents_non_shadow_from_deleting_admin_account(): void
    {
        $admin = $this->createUser('Admin', 'admin4@example.com', User::ROLE_ADMIN);
        $peerAdmin = $this->createUser('Peer Admin', 'peer4@example.com', User::ROLE_ADMIN);

        $this->assertSame(
            'Admin users cannot be deleted.',
            $this->service()->destroyError($admin, $peerAdmin)
        );
    }

    public function test_destroy_error_allows_shadow_to_delete_admin_account(): void
    {
        $shadow = $this->createUser('Shadow', 'shadow5@example.com', User::ROLE_SHADOW);
        $admin = $this->createUser('Admin', 'admin5@example.com', User::ROLE_ADMIN);

        $this->assertNull($this->service()->destroyError($shadow, $admin));
    }

    public function test_toggle_status_error_prevents_self_deactivation(): void
    {
        $admin = $this->createUser('Admin', 'admin6@example.com', User::ROLE_ADMIN);

        $this->assertSame(
            'You cannot deactivate your own account.',
            $this->service()->toggleStatusError($admin, $admin)
        );
    }

    public function test_resolve_return_url_rejects_external_hosts_and_falls_back_to_default(): void
    {
        $client = $this->createClient('Return Url Client', 'return-url@example.com');

        $resolved = $this->service()->resolveReturnUrl('https://evil.example.com/steal', $client);

        $this->assertSame(route('admin.users.clients', absolute: false), $resolved);
    }

    public function test_resolve_return_url_rejects_protocol_relative_urls(): void
    {
        $client = $this->createClient('Return Url Client 2', 'return-url2@example.com');

        $resolved = $this->service()->resolveReturnUrl('//evil.example.com/steal', $client);

        $this->assertSame(route('admin.users.clients', absolute: false), $resolved);
    }

    public function test_resolve_return_url_accepts_known_internal_paths(): void
    {
        $client = $this->createClient('Return Url Client 3', 'return-url3@example.com');

        $resolved = $this->service()->resolveReturnUrl('/admin/users?tab=staff', $client);

        $this->assertSame('/admin/users?tab=staff', $resolved);
    }

    public function test_resolve_return_url_rejects_unknown_internal_paths(): void
    {
        $client = $this->createClient('Return Url Client 4', 'return-url4@example.com');

        $resolved = $this->service()->resolveReturnUrl('/admin/settings/danger-zone', $client);

        $this->assertSame(route('admin.users.clients', absolute: false), $resolved);
    }
}
