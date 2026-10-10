<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\AdminSeeder;
use Database\Seeders\ShadowSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SeederPasswordPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeders_apply_role_based_password_policy_in_fixed_mode(): void
    {
        config()->set('helpdesk.staff_default_password', 'staff-fixed-password');
        config()->set('helpdesk.client_password_mode', 'fixed');
        config()->set('helpdesk.client_default_password', 'client-fixed-password');
        config()->set('helpdesk.shadow_password', 'shadow-fixed-password');

        $this->seed([
            ShadowSeeder::class,
            AdminSeeder::class,
            UserSeeder::class,
        ]);

        $staffEmails = [
            'admin@example.com',
            'super.user@example.com',
            'technical@example.com',
        ];

        foreach ($staffEmails as $staffEmail) {
            /** @var User $staff */
            $staff = User::query()->where('email', $staffEmail)->firstOrFail();
            $this->assertTrue(Hash::check('staff-fixed-password', $staff->password));
            $this->assertTrue($staff->mustChangePassword());
        }

        $clientEmails = [
            'client.one@example.com',
            'client.two@example.com',
        ];

        foreach ($clientEmails as $clientEmail) {
            /** @var User $client */
            $client = User::query()->where('email', $clientEmail)->firstOrFail();
            $this->assertTrue(Hash::check('client-fixed-password', $client->password));
            $this->assertFalse($client->mustChangePassword());
        }

        /** @var User $shadow */
        $shadow = User::query()->where('email', 'shadow@example.com')->firstOrFail();
        $this->assertTrue(Hash::check('shadow-fixed-password', $shadow->password));
        $this->assertFalse($shadow->mustChangePassword());
    }

    public function test_seeders_generate_random_client_passwords_and_export_private_handoff_file(): void
    {
        Storage::fake('local');

        config()->set('helpdesk.staff_default_password', 'staff-fixed-password');
        config()->set('helpdesk.client_password_mode', 'random');
        config()->set('helpdesk.client_default_password', 'client-fixed-password');
        config()->set('helpdesk.shadow_password', 'shadow-fixed-password');
        config()->set('helpdesk.seed_client_credentials_disk', 'local');
        config()->set('helpdesk.seed_client_credentials_path', 'seeded-client-credentials');

        $this->seed([
            ShadowSeeder::class,
            AdminSeeder::class,
            UserSeeder::class,
        ]);

        $files = Storage::disk('local')->files('seeded-client-credentials');
        $this->assertCount(1, $files);

        $content = trim((string) Storage::disk('local')->get($files[0]));
        $lines = array_values(array_filter(array_map('trim', explode("\n", $content))));
        $this->assertGreaterThan(1, count($lines));
        $this->assertSame('email,password', $lines[0]);

        $credentialsByEmail = [];
        foreach (array_slice($lines, 1) as $line) {
            [$email, $password] = str_getcsv($line);
            $credentialsByEmail[$email] = $password;
        }

        $clientEmails = [
            'client.one@example.com',
            'client.two@example.com',
        ];

        foreach ($clientEmails as $clientEmail) {
            $this->assertArrayHasKey($clientEmail, $credentialsByEmail);
            $plainPassword = (string) $credentialsByEmail[$clientEmail];
            $this->assertSame(10, strlen($plainPassword));

            /** @var User $client */
            $client = User::query()->where('email', $clientEmail)->firstOrFail();
            $this->assertTrue(Hash::check($plainPassword, $client->password));
            $this->assertFalse($client->mustChangePassword());
        }
    }
}
