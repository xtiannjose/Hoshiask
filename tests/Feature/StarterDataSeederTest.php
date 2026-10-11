<?php

namespace Tests\Feature;

use App\Events\ProductionAdvisoriesRequested;
use App\Listeners\ReportStarterDataAdvisories;
use App\Models\Category;
use App\Models\Department;
use Database\Seeders\StarterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StarterDataSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_new_database_gets_the_starter_departments_and_categories(): void
    {
        // The test case seeds the same data a fresh installation gets.
        $this->assertSame(count(StarterDataSeeder::DEPARTMENTS), Department::query()->count());
        $this->assertTrue(Department::query()->where('name', 'Hoshishi')->exists());
        $this->assertSame(5, Category::query()->count());
        $this->assertTrue(Category::query()->where('name', 'Hardware')->exists());
    }

    public function test_running_it_again_changes_nothing_and_never_brings_back_what_was_deleted(): void
    {
        Department::query()->where('name', 'Dune')->delete();
        Category::query()->where('name', 'Other')->delete();
        Category::query()->where('name', 'Software')->update(['description' => 'Edited by an administrator']);

        $this->seed(StarterDataSeeder::class);

        $this->assertFalse(Department::query()->where('name', 'Dune')->exists());
        $this->assertFalse(Category::query()->where('name', 'Other')->exists());
        $this->assertSame('Edited by an administrator', Category::query()->where('name', 'Software')->value('description'));
        $this->assertSame(count(StarterDataSeeder::DEPARTMENTS) - 1, Department::query()->count());
    }

    public function test_an_empty_table_is_filled_again(): void
    {
        Department::query()->delete();
        Category::query()->delete();

        $this->seed(StarterDataSeeder::class);

        $this->assertSame(count(StarterDataSeeder::DEPARTMENTS), Department::query()->count());
        $this->assertSame(5, Category::query()->count());
    }

    public function test_the_status_check_names_a_database_without_departments(): void
    {
        $request = new ProductionAdvisoriesRequested;
        (new ReportStarterDataAdvisories)->handle($request);
        $this->assertSame([], $request->advisories());

        Department::query()->delete();

        $request = new ProductionAdvisoriesRequested;
        (new ReportStarterDataAdvisories)->handle($request);
        $this->assertCount(1, $request->advisories());
        $this->assertStringContainsString('db:seed --class=StarterDataSeeder', $request->advisories()[0]);
    }
}
