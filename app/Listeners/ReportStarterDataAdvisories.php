<?php

namespace App\Listeners;

use App\Events\ProductionAdvisoriesRequested;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Plain-English note about the starter departments for the production status check. It never counts as a warning.
 */
class ReportStarterDataAdvisories
{
    public function handle(ProductionAdvisoriesRequested $request): void
    {
        $departments = rescue(static fn () => Schema::hasTable('departments') ? DB::table('departments')->count() : null, null, false);

        if ($departments === 0) {
            $request->add('There are no departments, so every user form refuses the department. A new installation gets them with `php artisan migrate --seed`; on this one run `php artisan db:seed --class=StarterDataSeeder` (it only fills an empty table).');
        }
    }
}
