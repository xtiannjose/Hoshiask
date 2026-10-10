<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\RecordsExports;
use App\Http\Controllers\Concerns\SanitizesCsvCells;
use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureSystemLogsUnlocked;
use App\Http\Requests\Admin\SystemLogs\UnlockSystemLogsRequest;
use App\Models\SystemLog;
use App\Models\User;
use App\Services\SystemLogService;
use App\Support\LikePattern;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SystemLogController extends Controller
{
    use RecordsExports;
    use SanitizesCsvCells;

    public function __construct(
        private SystemLogService $systemLogs,
    ) {}

    public function showUnlockForm(Request $request)
    {
        $returnTo = EnsureSystemLogsUnlocked::safeReturnPath($request->query('return_to'));

        return view('admin.system-logs.unlock', compact('returnTo'));
    }

    public function unlock(UnlockSystemLogsRequest $request)
    {
        /** @var User|null $user */
        $user = $request->user();
        if (! $user || ! $user->isShadow()) {
            abort(403);
        }

        if (! Hash::check($request->string('password')->toString(), $user->password)) {
            return back()->withErrors([
                'password' => 'Incorrect password.',
            ]);
        }

        $request->session()->put(
            EnsureSystemLogsUnlocked::SESSION_KEY,
            now()->addMinutes(20)->toDateTimeString()
        );

        $this->systemLogs->record(
            'system_logs.unlock',
            'Unlocked the system log viewer.',
            [
                'category' => 'system',
                'target_type' => User::class,
                'target_id' => $user->id,
            ]
        );

        $returnTo = EnsureSystemLogsUnlocked::safeReturnPath($request->input('return_to'));

        return $returnTo !== null
            ? redirect()->to($returnTo)
            : redirect()->route('admin.system-logs.index');
    }

    public function lock(Request $request)
    {
        $request->session()->forget(EnsureSystemLogsUnlocked::SESSION_KEY);

        $this->systemLogs->record(
            'system_logs.lock',
            'Locked the system log viewer.',
            [
                'category' => 'system',
                'target_type' => User::class,
                'target_id' => optional($request->user())->id,
            ]
        );

        return redirect()->route('admin.dashboard')
            ->with('success', 'Monitoring tools have been locked.');
    }

    public function index(Request $request)
    {
        $logs = $this->filteredLogsQuery($request)->paginate(40)->withQueryString();
        $categories = SystemLog::query()
            ->select('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category');
        $events = SystemLog::query()
            ->select('event_type')
            ->distinct()
            ->orderBy('event_type')
            ->pluck('event_type');

        return view('admin.system-logs.index', compact('logs', 'categories', 'events'));
    }

    public function export(Request $request): StreamedResponse
    {
        $rowCount = $this->filteredLogsQuery($request)->count();

        $this->systemLogs->record(
            'system_logs.exported',
            'Exported the audit trail to CSV.',
            [
                'category' => 'system',
                'metadata' => [
                    'row_count' => $rowCount,
                    'filters' => $this->exportedLogFilters($request),
                ],
                'request' => $request,
            ]
        );

        $filename = 'audit-trail-'.now()->format('Y-m-d-His').'.csv';

        return $this->streamCsvDownload($filename, function ($handle) use ($request): void {
            fputcsv($handle, ['Time', 'Actor', 'Actor Email', 'Category', 'Event', 'Description', 'IP Address', 'Metadata']);

            $this->filteredLogsQuery($request)->chunk(200, function ($logs) use ($handle): void {
                foreach ($logs as $log) {
                    fputcsv($handle, [
                        optional($log->occurred_at)->format('Y-m-d H:i:s'),
                        $this->csvCell(optional($log->actor)->name ?? 'System'),
                        $this->csvCell(optional($log->actor)->email ?? ''),
                        $this->csvCell($log->category),
                        $this->csvCell($log->event_type),
                        $this->csvCell($log->description),
                        $this->csvCell($log->ip_address),
                        $this->csvCell(! empty($log->metadata) ? json_encode($log->metadata, JSON_UNESCAPED_SLASHES) : ''),
                    ]);
                }
            });
        });
    }

    /**
     * The filters chosen for an export, without the typed search text (it can hold names and email addresses).
     *
     * @return array<string, string>
     */
    private function exportedLogFilters(Request $request): array
    {
        $filters = array_filter($request->only(['category', 'event']), fn ($value) => is_string($value) && $value !== '');

        if ($request->filled('search')) {
            $filters['search'] = self::EXPORT_TYPED_TEXT_MARK;
        }

        return $filters;
    }

    /**
     * @return Builder<SystemLog>
     */
    private function filteredLogsQuery(Request $request): Builder
    {
        $query = SystemLog::query()
            ->with('actor')
            ->orderByDesc('occurred_at')
            ->orderByDesc('id');

        if ($request->filled('category')) {
            $query->where('category', $request->string('category')->toString());
        }

        if ($request->filled('event')) {
            $query->where('event_type', $request->string('event')->toString());
        }

        if ($request->filled('search')) {
            $like = LikePattern::contains(mb_strtolower($request->string('search')->toString()));
            $escape = LikePattern::LIKE_ESCAPE;

            $query->where(function ($builder) use ($like, $escape) {
                $builder->whereRaw("LOWER(description) LIKE ? {$escape}", [$like])
                    ->orWhereRaw("LOWER(event_type) LIKE ? {$escape}", [$like])
                    ->orWhereHas('actor', function ($userQuery) use ($like, $escape) {
                        $userQuery->whereRaw("LOWER(name) LIKE ? {$escape}", [$like])
                            ->orWhereRaw("LOWER(email) LIKE ? {$escape}", [$like]);
                    });
            });
        }

        return $query;
    }
}
