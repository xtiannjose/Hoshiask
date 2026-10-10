<?php

namespace App\Services\TicketImport;

use Illuminate\Support\Carbon;

class HelpdeskTrackerDescriptionFormatter
{
    public function buildSubject(?string $projectName, ?string $issueDescription, ?string $issueVia = null): string
    {
        $projectName = $this->normalizeNullableString($projectName);
        $issueDescription = $this->normalizeNullableString($issueDescription);
        $issueVia = $this->normalizeNullableString($issueVia);
        $issueSummary = $issueDescription ?? $issueVia;

        return match (true) {
            $projectName !== null && $issueSummary !== null => $projectName.' - '.$issueSummary,
            $issueSummary !== null => $issueSummary,
            $projectName !== null => $projectName,
            default => 'Imported Helpdesk Ticket',
        };
    }

    /**
     * @param  array{
     *     date_received: string|null,
     *     time_received: string|null,
     *     date_resolved: string|null,
     *     time_resolved: string|null,
     *     issue_via: string|null,
     *     requestor_details: string|null,
     *     project_name: string|null,
     *     issue_description: string|null,
     *     resolution: string|null,
     *     attended_by: string|null
     * }  $fields
     */
    public function buildDescription(array $fields, Carbon $createdAt, ?Carbon $completedAt, string $displayTimezone): string
    {
        $createdAtDisplay = $createdAt->copy()->setTimezone($displayTimezone);
        $completedAtDisplay = $completedAt?->copy()->setTimezone($displayTimezone);

        $lines = [];

        $this->appendLine($lines, 'Date Received', $this->formatDisplayDate($fields['date_received'] ?? null) ?? $createdAtDisplay->format('m/d/Y'));
        $this->appendLine($lines, 'Time Received', $this->formatDisplayTime($fields['time_received'] ?? null) ?? $createdAtDisplay->format('g:i:s A'));
        $this->appendLine($lines, 'Date Resolved', $this->formatDisplayDate($fields['date_resolved'] ?? null) ?? $completedAtDisplay?->format('m/d/Y'));
        $this->appendLine($lines, 'Time Resolved', $this->formatDisplayTime($fields['time_resolved'] ?? null) ?? $completedAtDisplay?->format('g:i:s A'));
        $this->appendLine($lines, 'Issue Via', $this->normalizeDisplayText($fields['issue_via'] ?? null));
        $this->appendLine($lines, 'Requestor Details', $this->normalizeDisplayText($fields['requestor_details'] ?? null));
        $this->appendLine($lines, 'Project Name', $this->normalizeDisplayText($fields['project_name'] ?? null));
        $this->appendLine($lines, 'Issue Description', $this->normalizeDisplayText($fields['issue_description'] ?? null));
        $this->appendLine($lines, 'Resolution', $this->normalizeDisplayText($fields['resolution'] ?? null));
        $this->appendLine($lines, 'Attended By', $this->normalizeDisplayText($fields['attended_by'] ?? null));
        $this->appendLine($lines, 'Created At', $createdAtDisplay->format('m/d/Y g:i:s A'));
        $this->appendLine($lines, 'Completed At', $completedAtDisplay?->format('m/d/Y g:i:s A'));

        return implode(PHP_EOL, $lines);
    }

    public function combineDateAndTime(?string $date, ?string $time, string $sourceTimezone): ?Carbon
    {
        $date = $this->normalizeNullableString($date);
        $time = $this->normalizeNullableString($time);

        if ($date === null && $time === null) {
            return null;
        }

        if ($date === null) {
            return null;
        }

        $datePart = $this->parseDateOnly($date, $sourceTimezone);
        $dateTimeInput = $datePart->format('Y-m-d').' '.($this->normalizeTimeToken($time) ?? '12:00:00 AM');

        return Carbon::parse($dateTimeInput, $sourceTimezone)
            ->setTimezone((string) config('app.timezone', 'UTC'));
    }

    public function formatExcelDate(float $serial): string
    {
        return $this->excelBaseDate()
            ->addDays((int) floor($serial))
            ->format('m/d/Y');
    }

    public function formatExcelTime(float $serial): string
    {
        $seconds = (int) round(fmod($serial, 1.0) * 86400);

        return $this->excelBaseDate()
            ->addSeconds($seconds)
            ->format('g:i:s A');
    }

    public function formatExcelDateTime(float $serial): string
    {
        $days = (int) floor($serial);
        $seconds = (int) round(fmod($serial, 1.0) * 86400);

        return $this->excelBaseDate()
            ->addDays($days)
            ->addSeconds($seconds)
            ->format('m/d/Y g:i:s A');
    }

    private function excelBaseDate(): Carbon
    {
        return Carbon::create(1899, 12, 30, 0, 0, 0, 'UTC');
    }

    private function parseDateOnly(string $value, string $sourceTimezone): Carbon
    {
        foreach (['m/d/Y', 'n/j/Y', 'Y-m-d'] as $format) {
            try {
                return Carbon::createFromFormat($format, $value, $sourceTimezone)->startOfDay();
            } catch (\Throwable) {
                continue;
            }
        }

        return Carbon::parse($value, $sourceTimezone)->startOfDay();
    }

    private function formatDisplayDate(?string $value): ?string
    {
        $value = $this->normalizeNullableString($value);
        if ($value === null) {
            return null;
        }

        try {
            return $this->parseDateOnly($value, 'Asia/Manila')->format('m/d/Y');
        } catch (\Throwable) {
            return $value;
        }
    }

    private function formatDisplayTime(?string $value): ?string
    {
        $value = $this->normalizeTimeToken($value);
        if ($value === null) {
            return null;
        }

        try {
            $parsed = Carbon::parse($value, 'Asia/Manila');
            $includesSeconds = (bool) preg_match('/:\d{2}:\d{2}\b/', $value);

            return $parsed->format($includesSeconds ? 'g:i:s A' : 'g:i A');
        } catch (\Throwable) {
            return $value;
        }
    }

    private function normalizeTimeToken(?string $value): ?string
    {
        $value = $this->normalizeDisplayText($value);
        if ($value === null) {
            return null;
        }

        return trim($value);
    }

    private function normalizeDisplayText(?string $value): ?string
    {
        $value = $this->normalizeNullableString($value);
        if ($value === null) {
            return null;
        }

        $normalized = preg_replace_callback(
            '/\b(\d{1,2}:\d{2}(?::\d{2})?)\s+NN\b/i',
            static fn (array $matches): string => $matches[1].' PM',
            $value,
        ) ?? $value;
        $normalized = preg_replace_callback(
            '/\b(\d{1,2}:\d{2}(?::\d{2})?)\s+MN\b/i',
            static fn (array $matches): string => $matches[1].' AM',
            $normalized,
        ) ?? $normalized;

        return trim($normalized);
    }

    private function normalizeNullableString(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim(preg_replace('/\s+/u', ' ', $value) ?? $value);

        return $normalized === '' ? null : $normalized;
    }

    /**
     * @param  list<string>  $lines
     */
    private function appendLine(array &$lines, string $label, ?string $value): void
    {
        $value = $this->normalizeNullableString($value);
        if ($value === null) {
            return;
        }

        $lines[] = $label.': '.$value;
    }
}
