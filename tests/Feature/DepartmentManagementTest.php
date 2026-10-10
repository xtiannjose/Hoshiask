<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DepartmentManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_department_with_logo_and_assign_it_to_staff_user(): void
    {
        Storage::fake('department-logos');

        $admin = User::create([
            'name' => 'Department Admin',
            'username' => 'department.admin',
            'email' => 'department-admin@example.com',
            'phone' => '09110000001',
            'department' => 'Hoshishi',
            'role' => User::ROLE_ADMIN,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->post(route('admin.departments.store'), [
            'name' => 'Meadow',
            'logo' => UploadedFile::fake()->create('meadow.png', 12, 'image/png'),
        ]);

        $response->assertRedirect(route('admin.departments.index'));
        $response->assertSessionHas('success', 'Department created successfully.');

        $department = Department::query()->where('name', 'Meadow')->firstOrFail();

        $this->assertSame('meadow', $department->slug);
        $this->assertStringStartsWith('storage/department-logos/meadow-', (string) $department->logo_path);
        Storage::disk('department-logos')->assertExists($department->managedLogoStoragePath());

        $createUserResponse = $this->actingAs($admin)->post(route('admin.users.store'), [
            'username' => 'meadow.technical',
            'name' => 'Meadow Technical',
            'email' => 'meadow-technical@example.com',
            'phone' => '09110000002',
            'department' => 'Meadow',
            'role' => User::ROLE_TECHNICAL,
            'password' => 'purple-giraffe-lamp',
            'password_confirmation' => 'purple-giraffe-lamp',
        ]);

        $createUserResponse->assertRedirect(route('admin.users.index', [
            'search' => 'meadow.technical',
        ]));

        $this->assertDatabaseHas('users', [
            'username' => 'meadow.technical',
            'role' => User::ROLE_TECHNICAL,
            'department' => 'Meadow',
        ]);
    }

    public function test_admin_can_rename_department_and_existing_users_follow_the_new_name(): void
    {
        $admin = User::create([
            'name' => 'Rename Admin',
            'username' => 'rename.admin',
            'email' => 'rename-admin@example.com',
            'phone' => '09110000003',
            'department' => 'Hoshishi',
            'role' => User::ROLE_ADMIN,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $department = Department::query()->create([
            'name' => 'NIA',
            'slug' => 'nia',
            'logo_path' => 'images/Others Logo.png',
        ]);

        $staff = User::create([
            'name' => 'NIA Staff',
            'username' => 'nia.staff',
            'email' => 'nia-staff@example.com',
            'phone' => '09110000004',
            'department' => 'NIA',
            'role' => User::ROLE_TECHNICAL,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $client = User::create([
            'name' => 'NIA Client',
            'username' => 'nia.client',
            'email' => 'nia-client@example.com',
            'phone' => '09110000005',
            'department' => 'NIA',
            'role' => User::ROLE_CLIENT,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->put(route('admin.departments.update', $department), [
            'name' => 'NICA',
        ]);

        $response->assertRedirect(route('admin.departments.index'));
        $response->assertSessionHas('success', 'Department updated successfully.');

        $department->refresh();
        $staff->refresh();
        $client->refresh();

        $this->assertSame('NICA', $department->name);
        $this->assertSame('nica', $department->slug);
        $this->assertSame('NICA', $staff->department);
        $this->assertSame('NICA', $client->department);
    }

    public function test_super_user_cannot_access_department_management(): void
    {
        $superUser = User::create([
            'name' => 'Super User',
            'username' => 'super.user',
            'email' => 'super-user-departments@example.com',
            'phone' => '09110000006',
            'department' => 'Hoshishi',
            'role' => User::ROLE_SUPER_USER,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $response = $this->actingAs($superUser)->get(route('admin.departments.index'));

        $response->assertForbidden();
    }

    public function test_department_logo_upload_rejects_svg_files(): void
    {
        Storage::fake('department-logos');

        $admin = User::create([
            'name' => 'Svg Admin',
            'username' => 'svg.admin',
            'email' => 'svg-admin@example.com',
            'phone' => '09110000007',
            'department' => 'Hoshishi',
            'role' => User::ROLE_ADMIN,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->post(route('admin.departments.store'), [
            'name' => 'Unsafe Logo',
            'logo' => UploadedFile::fake()->create('unsafe.svg', 1, 'image/svg+xml'),
        ]);

        $response->assertSessionHasErrors('logo');
        $this->assertDatabaseMissing('departments', [
            'name' => 'Unsafe Logo',
        ]);
    }

    public function test_department_names_are_unique_regardless_of_letter_case(): void
    {
        $admin = User::create([
            'name' => 'Case Admin',
            'username' => 'case.admin',
            'email' => 'case-admin@example.com',
            'phone' => '09110000008',
            'department' => 'Hoshishi',
            'role' => User::ROLE_ADMIN,
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        // "Glade" is one of the starter departments.
        $this->actingAs($admin)->post(route('admin.departments.store'), [
            'name' => 'glade',
            'logo' => UploadedFile::fake()->create('glade.png', 12, 'image/png'),
        ])->assertSessionHasErrors(['name' => 'The name has already been taken.']);

        $this->assertSame(1, Department::query()->whereRaw('LOWER(name) = ?', ['glade'])->count());

        $department = Department::query()->create(['name' => 'Case Test', 'slug' => 'case-test']);

        // Renaming another department onto an existing name (any case) is rejected...
        $this->actingAs($admin)->put(route('admin.departments.update', $department), ['name' => 'jUnIpEr'])
            ->assertSessionHasErrors(['name' => 'The name has already been taken.']);

        // ...but a department may change the case of its own name.
        $this->actingAs($admin)->put(route('admin.departments.update', $department), ['name' => 'CASE TEST'])
            ->assertSessionHasNoErrors();
        $this->assertSame('CASE TEST', $department->fresh()->name);
    }

    public function test_department_resolved_logo_uses_public_storage_location_when_file_exists(): void
    {
        $directory = public_path('storage/department-logos');
        File::ensureDirectoryExists($directory);

        $filename = 'department-test-logo.txt';
        $fullPath = $directory.DIRECTORY_SEPARATOR.$filename;
        File::put($fullPath, 'logo');

        try {
            $department = Department::query()->create([
                'name' => 'Logo Check',
                'slug' => 'logo-check',
                'logo_path' => 'storage/department-logos/'.$filename,
            ]);

            $this->assertSame('storage/department-logos/'.$filename, $department->resolved_logo_path);
            $this->assertStringContainsString('/storage/department-logos/'.$filename, $department->logo_url);
        } finally {
            File::delete($fullPath);
        }
    }
}
