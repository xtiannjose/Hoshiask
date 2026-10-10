<?php

namespace App\Services\TicketImport;

use RuntimeException;
use Traversable;
use ZipArchive;

class HelpdeskTrackerXlsxParser
{
    /**
     * @var array<string, list<string>>
     */
    private const REQUIRED_HEADER_ALIASES = [
        'date_received' => ['date received'],
        'time_received' => ['time received'],
        'issue_via' => [
            'issue / request reported via (email, call, sms, online messaging)',
            'issue / request reported via',
        ],
        'project_name' => ['name of project'],
        'issue_description' => ['issue / request description'],
        'date_resolved' => ['date resolved'],
        'time_resolved' => ['time resolved'],
        'resolution' => ['issue / request resolution'],
        'attended_by' => ['attended by'],
    ];

    /**
     * @var array<string, list<string>>
     */
    private const OPTIONAL_HEADER_ALIASES = [
        'ticket_number' => ['ticket number', 'ticket no', 'ticket no.', 'ticket #', 'reference number', 'reference no'],
        'requestor_details' => ['requestor details', 'requester details', 'requestor detail', 'requester detail'],
    ];

    /**
     * @return Traversable<int, array{
     *     source_sheet: string,
     *     source_row: int,
     *     values: array<string, string|null>
     * }>
     */
    public function parse(string $path): Traversable
    {
        $archive = new ZipArchive;
        if ($archive->open($path) !== true) {
            throw new RuntimeException('Unable to open spreadsheet: '.$path);
        }

        try {
            $sharedStrings = $this->loadSharedStrings($archive);
            $formats = $this->loadStyleFormats($archive);
            $worksheets = $this->loadWorksheets($archive);

            foreach ($worksheets as $worksheet) {
                yield from $this->readWorksheetRows(
                    archive: $archive,
                    worksheetPath: $worksheet['path'],
                    sheetName: $worksheet['name'],
                    sharedStrings: $sharedStrings,
                    formats: $formats,
                );
            }
        } finally {
            $archive->close();
        }
    }

    /**
     * @return array<int, string>
     */
    private function loadSharedStrings(ZipArchive $archive): array
    {
        $xml = $this->loadXml($archive, 'xl/sharedStrings.xml');
        if ($xml === null) {
            return [];
        }

        $xml->registerXPathNamespace('main', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $sharedStrings = [];

        foreach ($xml->xpath('//main:si') ?: [] as $stringNode) {
            $stringNode->registerXPathNamespace('main', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
            $textParts = $stringNode->xpath('.//main:t') ?: [];
            $sharedStrings[] = trim(implode('', array_map(
                static fn ($part) => (string) $part,
                $textParts,
            )));
        }

        return $sharedStrings;
    }

    /**
     * @return array<int, array{type: string}>
     */
    private function loadStyleFormats(ZipArchive $archive): array
    {
        $xml = $this->loadXml($archive, 'xl/styles.xml');
        if ($xml === null) {
            return [];
        }

        $xml->registerXPathNamespace('main', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $customFormats = [];

        foreach ($xml->xpath('//main:numFmts/main:numFmt') ?: [] as $numFmt) {
            $customFormats[(int) $numFmt['numFmtId']] = (string) $numFmt['formatCode'];
        }

        $formats = [];
        foreach ($xml->xpath('//main:cellXfs/main:xf') ?: [] as $index => $xf) {
            $numFmtId = (int) $xf['numFmtId'];
            $formatCode = $customFormats[$numFmtId] ?? $this->builtinFormatCode($numFmtId);
            $formats[(int) $index] = [
                'type' => $this->inferFormatType($formatCode, $numFmtId),
            ];
        }

        return $formats;
    }

    /**
     * @return list<array{name: string, path: string}>
     */
    private function loadWorksheets(ZipArchive $archive): array
    {
        $workbook = $this->loadXml($archive, 'xl/workbook.xml');
        $rels = $this->loadXml($archive, 'xl/_rels/workbook.xml.rels');

        if (! $workbook instanceof \SimpleXMLElement || ! $rels instanceof \SimpleXMLElement) {
            throw new RuntimeException('Workbook metadata is missing from the spreadsheet.');
        }

        $workbook->registerXPathNamespace('main', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $workbook->registerXPathNamespace('r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');
        $rels->registerXPathNamespace('rel', 'http://schemas.openxmlformats.org/package/2006/relationships');

        $targetsByRelationshipId = [];
        foreach ($rels->xpath('//rel:Relationship') ?: [] as $relationship) {
            $targetsByRelationshipId[(string) $relationship['Id']] = 'xl/'.ltrim((string) $relationship['Target'], '/');
        }

        $worksheets = [];
        foreach ($workbook->xpath('//main:sheets/main:sheet') ?: [] as $sheet) {
            $relationshipId = (string) $sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];
            $target = $targetsByRelationshipId[$relationshipId] ?? null;

            if ($target === null) {
                continue;
            }

            $worksheets[] = [
                'name' => trim((string) $sheet['name']),
                'path' => $target,
            ];
        }

        return $worksheets;
    }

    /**
     * @param  array<int, string>  $sharedStrings
     * @param  array<int, array{type: string}>  $formats
     * @return Traversable<int, array{
     *     source_sheet: string,
     *     source_row: int,
     *     values: array<string, string|null>
     * }>
     */
    private function readWorksheetRows(
        ZipArchive $archive,
        string $worksheetPath,
        string $sheetName,
        array $sharedStrings,
        array $formats,
    ): Traversable {
        $xml = $this->loadXml($archive, $worksheetPath);
        if (! $xml instanceof \SimpleXMLElement) {
            throw new RuntimeException('Worksheet not found: '.$worksheetPath);
        }

        $xml->registerXPathNamespace('main', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');

        $headerMap = null;

        foreach ($xml->xpath('//main:sheetData/main:row') ?: [] as $rowNode) {
            $rowNumber = (int) $rowNode['r'];
            $cells = $this->extractCellsForRow($rowNode, $sharedStrings, $formats);

            if ($headerMap === null) {
                $headerMap = $this->resolveHeaderMap($cells);

                continue;
            }

            $values = [];
            foreach ($headerMap as $column => $field) {
                $values[$field] = $cells[$column]['formatted'] ?? null;
            }

            if ($this->rowIsBlank($values)) {
                continue;
            }

            yield [
                'source_sheet' => $sheetName,
                'source_row' => $rowNumber,
                'values' => $values,
            ];
        }
    }

    /**
     * @param  array<string, array{formatted: string|null}>  $cells
     * @return array<string, string>|null
     */
    private function resolveHeaderMap(array $cells): ?array
    {
        $matches = [];
        $requiredMatches = [];

        foreach ($cells as $column => $cell) {
            $header = $this->normalizeHeader($cell['formatted'] ?? null);
            if ($header === null) {
                continue;
            }

            foreach (self::REQUIRED_HEADER_ALIASES as $field => $aliases) {
                foreach ($aliases as $alias) {
                    if ($header === $this->normalizeHeader($alias)) {
                        $matches[$column] = $field;
                        $requiredMatches[$field] = true;
                    }
                }
            }

            foreach (self::OPTIONAL_HEADER_ALIASES as $field => $aliases) {
                foreach ($aliases as $alias) {
                    if ($header === $this->normalizeHeader($alias)) {
                        $matches[$column] = $field;
                    }
                }
            }
        }

        return count($requiredMatches) >= count(self::REQUIRED_HEADER_ALIASES) ? $matches : null;
    }

    /**
     * @param  array<int, string>  $sharedStrings
     * @param  array<int, array{type: string}>  $formats
     * @return array<string, array{formatted: string|null}>
     */
    private function extractCellsForRow(\SimpleXMLElement $rowNode, array $sharedStrings, array $formats): array
    {
        $rowNode->registerXPathNamespace('main', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $cells = [];

        foreach ($rowNode->xpath('./main:c') ?: [] as $cellNode) {
            $reference = (string) $cellNode['r'];
            $column = preg_replace('/\d+/', '', $reference) ?: '';
            if ($column === '') {
                continue;
            }

            $rawValue = $this->extractRawCellValue($cellNode, $sharedStrings);
            $style = $formats[(int) ($cellNode['s'] ?? 0)] ?? ['type' => 'general'];

            $cells[$column] = [
                'formatted' => $this->formatCellValue($rawValue, (string) ($cellNode['t'] ?? ''), $style['type']),
            ];
        }

        return $cells;
    }

    /**
     * @param  array<int, string>  $sharedStrings
     */
    private function extractRawCellValue(\SimpleXMLElement $cellNode, array $sharedStrings): ?string
    {
        $type = (string) ($cellNode['t'] ?? '');

        return match ($type) {
            's' => $sharedStrings[(int) $cellNode->v] ?? null,
            'inlineStr' => $this->extractInlineString($cellNode),
            default => isset($cellNode->v) ? trim((string) $cellNode->v) : null,
        };
    }

    private function extractInlineString(\SimpleXMLElement $cellNode): ?string
    {
        $cellNode->registerXPathNamespace('main', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $parts = $cellNode->xpath('./main:is//main:t') ?: [];
        $text = trim(implode('', array_map(static fn ($part) => (string) $part, $parts)));

        return $text === '' ? null : $text;
    }

    private function formatCellValue(?string $rawValue, string $type, string $formatType): ?string
    {
        if ($rawValue === null || $rawValue === '') {
            return null;
        }

        if ($type === 's' || $type === 'inlineStr' || $type === 'str') {
            return trim($rawValue);
        }

        if (! is_numeric($rawValue)) {
            return trim($rawValue);
        }

        $serial = (float) $rawValue;
        $formatter = app(HelpdeskTrackerDescriptionFormatter::class);

        return match ($formatType) {
            'date' => $formatter->formatExcelDate($serial),
            'time' => $formatter->formatExcelTime($serial),
            'datetime' => $formatter->formatExcelDateTime($serial),
            default => $this->normalizeNumericString($rawValue),
        };
    }

    private function normalizeNumericString(string $value): string
    {
        if (! str_contains($value, '.')) {
            return $value;
        }

        $normalized = rtrim(rtrim($value, '0'), '.');

        return $normalized === '' ? '0' : $normalized;
    }

    private function inferFormatType(?string $formatCode, int $numFmtId): string
    {
        if ($formatCode === null) {
            return match (true) {
                in_array($numFmtId, [14, 15, 16, 17], true) => 'date',
                in_array($numFmtId, [18, 19, 20, 21, 45, 46, 47], true) => 'time',
                in_array($numFmtId, [22], true) => 'datetime',
                default => 'general',
            };
        }

        $normalized = strtolower(preg_replace('/\[[^\]]+\]/', '', $formatCode) ?? $formatCode);
        $hasDateTokens = str_contains($normalized, 'y') || str_contains($normalized, 'd');
        $hasTimeTokens = str_contains($normalized, 'h') || str_contains($normalized, 'am/pm') || str_contains($normalized, 'a/p');

        return match (true) {
            $hasDateTokens && $hasTimeTokens => 'datetime',
            $hasDateTokens => 'date',
            $hasTimeTokens => 'time',
            default => 'general',
        };
    }

    private function builtinFormatCode(int $numFmtId): ?string
    {
        return match ($numFmtId) {
            14 => 'm/d/yyyy',
            15 => 'd-mmm-yy',
            16 => 'd-mmm',
            17 => 'mmm-yy',
            18 => 'h:mm AM/PM',
            19 => 'h:mm:ss AM/PM',
            20 => 'h:mm',
            21 => 'h:mm:ss',
            22 => 'm/d/yyyy h:mm',
            45 => 'mm:ss',
            46 => '[h]:mm:ss',
            47 => 'mmss.0',
            default => null,
        };
    }

    private function loadXml(ZipArchive $archive, string $entryName): ?\SimpleXMLElement
    {
        $content = $archive->getFromName($entryName);
        if (! is_string($content) || trim($content) === '') {
            return null;
        }

        $xml = simplexml_load_string($content);

        if (! $xml instanceof \SimpleXMLElement) {
            throw new RuntimeException('Invalid spreadsheet XML: '.$entryName);
        }

        return $xml;
    }

    /**
     * @param  array<string, string|null>  $values
     */
    private function rowIsBlank(array $values): bool
    {
        foreach ($values as $value) {
            if ($value !== null && trim($value) !== '') {
                return false;
            }
        }

        return true;
    }

    private function normalizeHeader(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = strtolower(trim((string) preg_replace('/\s+/u', ' ', $value)));

        return $normalized === '' ? null : $normalized;
    }
}
