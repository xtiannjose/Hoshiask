<?php

namespace Tests\Unit;

use App\Models\CredentialHandoff;
use App\Models\User;
use App\Services\Admin\ManagedUserCredentialService;
use App\Services\SystemLogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use Tests\Traits\CreatesTestData;

class ManagedUserCredentialServiceTest extends TestCase
{
    use CreatesTestData;
    use RefreshDatabase;

    private function service(): ManagedUserCredentialService
    {
        return new ManagedUserCredentialService(app(SystemLogService::class));
    }

    public function test_issuing_a_temporary_password_hashes_it_and_forces_password_change_for_staff(): void
    {
        $shadow = $this->createUser('Shadow Admin', 'shadow@example.com', User::ROLE_SHADOW);
        $technical = $this->createUser('Tech User', 'tech@example.com', User::ROLE_TECHNICAL, overrides: [
            'must_change_password' => false,
        ]);
        $originalPasswordHash = $technical->password;

        $this->service()->issueTemporaryPassword($technical, $shadow);
        $technical->refresh();

        $this->assertNotSame($originalPasswordHash, $technical->password);
        $this->assertTrue((bool) $technical->must_change_password);

        $handoff = CredentialHandoff::query()->where('target_user_id', $technical->id)->sole();
        $this->assertSame($shadow->id, $handoff->issued_by_user_id);
        $this->assertNull($handoff->revealed_at);
        $this->assertNull($handoff->consumed_at);
        $this->assertTrue($handoff->expires_at->isFuture());
    }

    public function test_issuing_a_temporary_password_invalidates_the_target_users_sessions(): void
    {
        $shadow = $this->createUser('Shadow Admin', 'shadow-sessions@example.com', User::ROLE_SHADOW);
        $technical = $this->createUser('Tech User', 'tech-sessions@example.com', User::ROLE_TECHNICAL);

        DB::table('sessions')->insert([
            'id' => 'stolen-session-id',
            'user_id' => $technical->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'test-agent',
            'payload' => base64_encode('irrelevant'),
            'last_activity' => time(),
        ]);

        $this->service()->issueTemporaryPassword($technical, $shadow);

        $this->assertSame(0, DB::table('sessions')->where('user_id', $technical->id)->count());
    }

    public function test_issuing_a_temporary_password_does_not_force_change_for_client(): void
    {
        $shadow = $this->createUser('Shadow Admin', 'shadow2@example.com', User::ROLE_SHADOW);
        $client = $this->createClient('Client User', 'client2@example.com');

        $this->service()->issueTemporaryPassword($client, $shadow);
        $client->refresh();

        $this->assertFalse((bool) $client->must_change_password);
    }

    public function test_revealing_a_temporary_password_returns_it_once_then_returns_null(): void
    {
        $shadow = $this->createUser('Shadow Admin', 'shadow3@example.com', User::ROLE_SHADOW);
        $technical = $this->createUser('Tech User', 'tech3@example.com', User::ROLE_TECHNICAL);

        $service = $this->service();
        $service->issueTemporaryPassword($technical, $shadow);

        $revealed = $service->revealTemporaryPassword($technical);
        $this->assertIsString($revealed);
        $this->assertNotSame('', $revealed);
        $this->assertTrue(Hash::check($revealed, $technical->fresh()->password));

        $secondReveal = $service->revealTemporaryPassword($technical);
        $this->assertNull($secondReveal);
    }

    public function test_revealing_returns_null_when_no_handoff_exists(): void
    {
        $technical = $this->createUser('Tech User', 'tech4@example.com', User::ROLE_TECHNICAL);

        $this->assertNull($this->service()->revealTemporaryPassword($technical));
    }

    public function test_revealing_returns_null_after_handoff_expires(): void
    {
        $shadow = $this->createUser('Shadow Admin', 'shadow5@example.com', User::ROLE_SHADOW);
        $technical = $this->createUser('Tech User', 'tech5@example.com', User::ROLE_TECHNICAL);

        $service = $this->service();
        $service->issueTemporaryPassword($technical, $shadow);

        CredentialHandoff::query()
            ->where('target_user_id', $technical->id)
            ->update(['expires_at' => now()->subMinute()]);

        $this->assertNull($service->revealTemporaryPassword($technical));
    }

    public function test_reissuing_a_temporary_password_invalidates_the_previous_one(): void
    {
        $shadow = $this->createUser('Shadow Admin', 'shadow6@example.com', User::ROLE_SHADOW);
        $technical = $this->createUser('Tech User', 'tech6@example.com', User::ROLE_TECHNICAL);

        $service = $this->service();
        $service->issueTemporaryPassword($technical, $shadow);
        $service->issueTemporaryPassword($technical, $shadow);

        $revealed = $service->revealTemporaryPassword($technical);
        $this->assertIsString($revealed);
        $this->assertTrue(Hash::check($revealed, $technical->fresh()->password));
        $this->assertSame(1, CredentialHandoff::query()->where('target_user_id', $technical->id)->count());
    }
}
