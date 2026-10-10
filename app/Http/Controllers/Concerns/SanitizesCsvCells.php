<?php

namespace App\Http\Controllers\Concerns;

use Symfony\Component\HttpFoundation\StreamedResponse;

trait SanitizesCsvCells
{
    /**
     * Defuses CSV/Excel formula injection: a leading =, +, -, or @ is treated by
     * spreadsheet apps as the start of a formula, so a cell built from user-controlled
     * text (a display name, ticket subject, etc.) could execute code for whoever opens
     * the export. Prefixing a single quote forces the cell to render as plain text.
     */
    private function csvCell(mixed $value): string
    {
        $cell = (string) $value;
        $trimmedCell = ltrim($cell);

        if ($trimmedCell !== '' && in_array($trimmedCell[0], ['=', '+', '-', '@'], true)) {
            return "'".$cell;
        }

        return $cell;
    }

    /**
     * Opens a CSV output stream (with the UTF-8 BOM Excel needs to detect the encoding),
     * hands the file handle to $writeRows to fputcsv into, then closes it.
     *
     * @param  callable(resource): void  $writeRows
     */
    private function streamCsvDownload(string $filename, callable $writeRows): StreamedResponse
    {
        return response()->streamDownload(function () use ($writeRows): void {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            fwrite($handle, "\xEF\xBB\xBF");
            $writeRows($handle);

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
