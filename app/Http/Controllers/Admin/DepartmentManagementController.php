<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Departments\StoreDepartmentRequest;
use App\Http\Requests\Admin\Departments\UpdateDepartmentRequest;
use App\Models\Department;
use App\Models\User;
use App\Services\SystemLogService;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class DepartmentManagementController extends Controller
{
    public function __construct(
        private SystemLogService $systemLogs,
    ) {}

    public function index()
    {
        $this->authorize('viewAny', Department::class);

        $userCounts = User::query()
            ->selectRaw('department, COUNT(*) as aggregate')
            ->whereNotNull('department')
            ->groupBy('department')
            ->pluck('aggregate', 'department');

        $ticketCounts = DB::table('tickets')
            ->selectRaw('department, COUNT(*) as aggregate')
            ->whereNotNull('department')
            ->groupBy('department')
            ->pluck('aggregate', 'department');

        $departments = Department::query()
            ->orderByRaw('LOWER(name)')
            ->get()
            ->map(function (Department $department) use ($userCounts, $ticketCounts) {
                $userCount = (int) ($userCounts[$department->name] ?? 0);
                $ticketCount = (int) ($ticketCounts[$department->name] ?? 0);

                $department->setAttribute('user_count', $userCount);
                $department->setAttribute('ticket_count', $ticketCount);
                $department->setAttribute('delete_blocked_reason', $department->deletionBlockedReason($userCount, $ticketCount));

                return $department;
            });

        return view('admin.departments.index', compact('departments'));
    }

    public function store(StoreDepartmentRequest $request)
    {
        $this->authorize('create', Department::class);

        $department = DB::transaction(function () use ($request) {
            $name = $request->string('name')->toString();
            $slug = Department::generateAvailableSlug($name);

            return Department::query()->create([
                'name' => $name,
                'slug' => $slug,
                'logo_path' => $this->storeUploadedLogo($request->file('logo'), $slug),
            ]);
        });

        $this->systemLogs->record(
            'department.created',
            'Created a department.',
            [
                'category' => 'configuration',
                'target_type' => Department::class,
                'target_id' => $department->id,
                'metadata' => [
                    'name' => $department->name,
                    'slug' => $department->slug,
                ],
                'request' => $request,
            ]
        );

        return redirect()->route('admin.departments.index')
            ->with('success', 'Department created successfully.');
    }

    public function edit(Department $department)
    {
        $this->authorize('update', $department);

        return view('admin.departments.edit', compact('department'));
    }

    public function update(UpdateDepartmentRequest $request, Department $department)
    {
        $this->authorize('update', $department);

        $previousName = $department->name;

        DB::transaction(function () use ($request, $department, $previousName) {
            $department->name = $request->string('name')->toString();
            $department->slug = Department::generateAvailableSlug($department->name, $department->id);

            if ($request->hasFile('logo')) {
                $department->deleteManagedLogoIfPresent();
                $department->logo_path = $this->storeUploadedLogo($request->file('logo'), $department->slug);
            }

            $department->save();

            if ($previousName !== $department->name) {
                User::query()
                    ->where('department', $previousName)
                    ->update(['department' => $department->name]);

                // Straight to the table: renaming a department is not activity on its tickets.
                DB::table('tickets')
                    ->where('department', $previousName)
                    ->update(['department' => $department->name]);
            }
        });

        $this->systemLogs->record(
            'department.updated',
            'Updated a department.',
            [
                'category' => 'configuration',
                'target_type' => Department::class,
                'target_id' => $department->id,
                'metadata' => [
                    'name' => $department->name,
                    'slug' => $department->slug,
                ],
                'request' => $request,
            ]
        );

        return redirect()->route('admin.departments.index')
            ->with('success', 'Department updated successfully.');
    }

    public function destroy(Request $request, Department $department)
    {
        $this->authorize('delete', $department);

        $blockedReason = $department->deletionBlockedReason();
        if ($blockedReason !== null) {
            return redirect()->route('admin.departments.index')
                ->with('error', "{$department->name} was not deleted. {$blockedReason}");
        }

        DB::transaction(fn () => $department->delete());
        $department->deleteManagedLogoIfPresent();

        $this->systemLogs->record(
            'department.deleted',
            'Deleted a department.',
            [
                'category' => 'configuration',
                'target_type' => Department::class,
                'target_id' => $department->id,
                'metadata' => [
                    'name' => $department->name,
                    'slug' => $department->slug,
                ],
                'request' => $request,
            ]
        );

        return redirect()->route('admin.departments.index')
            ->with('success', 'Department deleted.');
    }

    private function storeUploadedLogo(UploadedFile $logo, string $slug): string
    {
        $extension = strtolower($logo->extension() ?: 'png');
        $filename = $slug.'-'.Str::lower(Str::random(8)).'.'.$extension;

        $storedPath = Storage::disk('department-logos')->putFileAs('', $logo, $filename);
        if ($storedPath === false) {
            throw new RuntimeException('Department logo upload failed.');
        }

        return 'storage/department-logos/'.$storedPath;
    }
}
