<?php

namespace App\Http\Controllers\Concerns;

use App\Services\SystemLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Writes one audit entry for a data export: who asked (the actor and IP come from the request),
 * which filters they chose and how many rows the file holds. It records the export before the
 * download starts and never stores names, email addresses or ticket text (typed search text is
 * replaced by a note that it was used).
 */
trait RecordsExports
{
    /** Query-string keys that change the page being looked at, never what is exported. */
    private const EXPORT_IGNORED_QUERY_KEYS = ['page', 'per_page', 'partial', 'heartbeat', 'sort', 'direction', 'return_to'];

    /** Text a person typed. Names and email addresses end up in it, so only the fact that it was used is recorded. */
    public const EXPORT_TYPED_TEXT_KEYS = ['search', 'q'];

    public const EXPORT_TYPED_TEXT_MARK = '(typed text, not recorded)';

    private const EXPORT_MAX_FILTERS = 20;

    private const EXPORT_MAX_VALUE_LENGTH = 80;

    /**
     * @param  array<string, mixed>  $extra  Extra facts about the file, for example the month or layout.
     */
    private function recordExport(Request $request, string $event, string $description, int $rows, array $extra = []): void
    {
        app(SystemLogService::class)->record($event, $description, [
            'category' => 'export',
            'metadata' => ['rows' => $rows, 'filters' => $this->exportFilters($request)] + $extra,
            'request' => $request,
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function exportFilters(Request $request): array
    {
        $filters = [];

        foreach ($request->query() as $key => $value) {
            if (count($filters) >= self::EXPORT_MAX_FILTERS) {
                break;
            }

            if (in_array($key, self::EXPORT_IGNORED_QUERY_KEYS, true) || ! is_scalar($value) || trim((string) $value) === '') {
                continue;
            }

            $filters[Str::limit((string) $key, 40, '')] = in_array($key, self::EXPORT_TYPED_TEXT_KEYS, true)
                ? self::EXPORT_TYPED_TEXT_MARK
                : Str::limit(trim((string) $value), self::EXPORT_MAX_VALUE_LENGTH);
        }

        return $filters;
    }
}
