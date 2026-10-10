<?php

namespace Tests\Unit;

use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserDepartmentBrandTest extends TestCase
{
    use RefreshDatabase;

    public function test_unknown_client_department_falls_back_to_others_brand(): void
    {
        $brandKey = User::departmentBrandKey('Unknown Agency', User::ROLE_CLIENT);
        $brandAssets = User::departmentBrandAssets('Unknown Agency', User::ROLE_CLIENT);

        $this->assertSame('others', $brandKey);
        $this->assertSame('others', $brandAssets['key']);
        $this->assertSame('Others', $brandAssets['name']);
    }

    public function test_legacy_department_names_resolve_to_their_current_department(): void
    {
        $this->assertSame('Hoshishi', User::departmentBrandAssets('Administration', User::ROLE_SHADOW)['name']);
        $this->assertSame('Hoshishi', User::departmentBrandAssets('Hoshishi Corporation', User::ROLE_TECHNICAL)['name']);
        $this->assertSame('Harbor Town', User::departmentBrandAssets('Htown', User::ROLE_CLIENT)['name']);
        $this->assertSame('images/Harbor Town Logo.png', User::departmentBrandAssets('Htown', User::ROLE_CLIENT)['logo_path']);
    }

    public function test_staff_without_a_department_use_the_support_department(): void
    {
        $this->assertSame('hoshishi', User::departmentBrandKey('', User::ROLE_TECHNICAL));
        $this->assertSame('Hoshishi', User::departmentBrandAssets(null, User::ROLE_ADMIN)['name']);
    }

    public function test_allowed_departments_come_from_the_departments_table(): void
    {
        $this->assertContains('Glade', User::allowedDepartments());
        $this->assertNotContains('Meadow', User::allowedDepartments());

        Department::query()->create(['name' => 'Meadow', 'slug' => 'meadow', 'logo_path' => null]);

        $this->assertContains('Meadow', User::allowedDepartments());
    }

    public function test_custom_department_returns_dynamic_brand_assets(): void
    {
        Department::query()->create([
            'name' => 'Meadow',
            'slug' => 'meadow',
            'logo_path' => 'images/departments/meadow.png',
        ]);

        $brandKey = User::departmentBrandKey('Meadow', User::ROLE_TECHNICAL);
        $brandAssets = User::departmentBrandAssets('Meadow', User::ROLE_TECHNICAL);

        $this->assertSame('meadow', $brandKey);
        $this->assertSame('meadow', $brandAssets['key']);
        $this->assertSame('Meadow', $brandAssets['name']);
    }
}
