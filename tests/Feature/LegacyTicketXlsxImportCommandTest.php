<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Ticket;
use App\Models\TicketUserState;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Tests\Support\ImportFiles;
use Tests\TestCase;
use ZipArchive;

class LegacyTicketXlsxImportCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_xlsx_import_command_preserves_ticket_number_and_populates_clean_requester_snapshot_fields(): void
    {
        $superUser = User::create([
            'name' => 'Import Reviewing Super User',
            'username' => 'import.review.super.xlsx',
            'email' => 'import-review-super-xlsx@example.com',
            'department' => 'Hoshishi',
            'phone' => '09170000002',
            'role' => User::ROLE_SUPER_USER,
            'password' => 'password',
            'is_active' => true,
        ]);
        $requester = User::create([
            'name' => 'Legacy Import User',
            'username' => 'legacy.import.xlsx',
            'email' => 'legacy-import-xlsx@example.com',
            'department' => 'Hoshishi',
            'phone' => '09171234567',
            'role' => User::ROLE_CLIENT,
            'password' => 'password',
            'is_active' => true,
        ]);

        $supportUser = User::create([
            'name' => 'John Arnold Carrasco',
            'username' => 'john.carrasco',
            'email' => 'john.carrasco@example.com',
            'department' => 'Hoshishi',
            'phone' => '09170000001',
            'role' => User::ROLE_TECHNICAL,
            'password' => 'password',
            'is_active' => true,
        ]);

        Category::create([
            'name' => 'Other',
            'description' => 'General import category',
            'color' => '#6B7280',
            'is_active' => true,
        ]);

        $importPath = ImportFiles::path('legacy-tracker.xlsx');
        File::ensureDirectoryExists(dirname($importPath));
        $this->writeTrackerWorkbook($importPath, [
            'ticket_number' => 'TK-U3GFYDVS',
            'requestor_details' => implode(PHP_EOL, [
                'Name: Requester Snapshot',
                'Contact Number: 09998887777',
                'Email: requester@example.com',
                'Province: Agusan del Norte',
                'Municipality: Butuan City',
            ]),
        ]);

        $this->artisan('tickets:import', [
            'paths' => ['legacy-tracker.xlsx'],
            '--default-user' => (string) $requester->id,
            '--source-timezone' => 'Asia/Manila',
        ])->assertSuccessful();

        $ticket = Ticket::query()->sole();
        $state = TicketUserState::query()
            ->where('ticket_id', $ticket->id)
            ->where('user_id', $superUser->id)
            ->sole();

        $this->assertSame('TK-U3GFYDVS', $ticket->ticket_number);
        $this->assertSame('Sample Client - Set up Access point for starlink', $ticket->subject);
        $this->assertSame('Requester Snapshot', $ticket->name);
        $this->assertSame('09998887777', $ticket->contact_number);
        $this->assertSame('requester@example.com', $ticket->email);
        $this->assertSame('Agusan del Norte', $ticket->province);
        $this->assertSame('Butuan City', $ticket->municipality);
        $this->assertSame($supportUser->id, $ticket->assigned_to);
        $this->assertSame('closed', $ticket->status);
        $this->assertTrue($ticket->isImported());
        $this->assertTrue($ticket->created_at?->equalTo(Carbon::parse('2026-03-17 14:00:00', 'Asia/Manila')->utc()));
        $this->assertTrue($ticket->assigned_at?->equalTo(Carbon::parse('2026-03-17 14:00:00', 'Asia/Manila')->utc()));
        $this->assertTrue($ticket->resolved_at?->equalTo(Carbon::parse('2026-03-17 17:00:00', 'Asia/Manila')->utc()));
        $this->assertTrue($ticket->closed_at?->equalTo(Carbon::parse('2026-03-17 17:00:00', 'Asia/Manila')->utc()));
        $this->assertTrue($state->acknowledged_at?->equalTo(Carbon::parse('2026-03-17 14:00:00', 'Asia/Manila')->utc()));
        $this->assertStringContainsString('Requestor Details: Name: Requester Snapshot', (string) $ticket->description);
        $this->assertStringContainsString('Completed At: 03/17/2026 5:00:00 PM', (string) $ticket->description);
        $this->assertStringNotContainsString('1899', (string) $ticket->description);
    }

    public function test_xlsx_import_command_skips_existing_ticket_numbers_without_update_existing(): void
    {
        $requester = User::create([
            'name' => 'Legacy Import User',
            'username' => 'legacy.import.xlsx.dup',
            'email' => 'legacy-import-xlsx-dup@example.com',
            'department' => 'Hoshishi',
            'phone' => '09171234568',
            'role' => User::ROLE_CLIENT,
            'password' => 'password',
            'is_active' => true,
        ]);

        Category::create([
            'name' => 'Other',
            'description' => 'General import category',
            'color' => '#6B7280',
            'is_active' => true,
        ]);

        $importPath = ImportFiles::path('legacy-tracker-dup.xlsx');
        File::ensureDirectoryExists(dirname($importPath));
        $this->writeTrackerWorkbook($importPath, [
            'ticket_number' => 'TK-U3GFYDVS',
        ]);

        $this->artisan('tickets:import', [
            'paths' => ['legacy-tracker-dup.xlsx'],
            '--default-user' => (string) $requester->id,
            '--source-timezone' => 'Asia/Manila',
        ])->assertSuccessful();

        $this->artisan('tickets:import', [
            'paths' => ['legacy-tracker-dup.xlsx'],
            '--default-user' => (string) $requester->id,
            '--source-timezone' => 'Asia/Manila',
        ])->assertSuccessful();

        $this->assertSame(1, Ticket::query()->count());
    }

    public function test_xlsx_import_command_updates_existing_tickets_by_ticket_number_when_requested(): void
    {
        $requester = User::create([
            'name' => 'Legacy Import User',
            'username' => 'legacy.import.xlsx.update',
            'email' => 'legacy-import-xlsx-update@example.com',
            'department' => 'Hoshishi',
            'phone' => '09171234569',
            'role' => User::ROLE_CLIENT,
            'password' => 'password',
            'is_active' => true,
        ]);

        Category::create([
            'name' => 'Other',
            'description' => 'General import category',
            'color' => '#6B7280',
            'is_active' => true,
        ]);

        Ticket::create([
            'ticket_number' => 'TK-U3GFYDVS',
            'name' => 'Old Snapshot',
            'contact_number' => '09170000000',
            'email' => 'old@example.com',
            'province' => 'Old Province',
            'municipality' => 'Old City',
            'subject' => 'Old Imported Subject',
            'description' => 'Old imported description',
            'priority' => 'medium',
            'status' => 'open',
            'user_id' => $requester->id,
            'category_id' => Category::query()->value('id'),
            'created_at' => Carbon::parse('2026-03-17 08:00:00', 'Asia/Manila')->utc(),
            'updated_at' => Carbon::parse('2026-03-17 08:00:00', 'Asia/Manila')->utc(),
        ]);

        $importPath = ImportFiles::path('legacy-tracker-update.xlsx');
        File::ensureDirectoryExists(dirname($importPath));
        $this->writeTrackerWorkbook($importPath, [
            'ticket_number' => 'TK-U3GFYDVS',
            'issue_description' => 'Updated starlink access point setup',
            'requestor_details' => 'Name: Requester Snapshot',
        ]);

        $this->artisan('tickets:import', [
            'paths' => ['legacy-tracker-update.xlsx'],
            '--default-user' => (string) $requester->id,
            '--source-timezone' => 'Asia/Manila',
            '--update-existing' => true,
        ])->assertSuccessful();

        $ticket = Ticket::query()->sole();

        $this->assertSame('TK-U3GFYDVS', $ticket->ticket_number);
        $this->assertSame('Sample Client - Updated starlink access point setup', $ticket->subject);
        $this->assertSame('Requester Snapshot', $ticket->name);
        $this->assertCount(1, Ticket::query()->get());
    }

    private function writeTrackerWorkbook(string $path, array $overrides = []): void
    {
        $row = array_merge([
            'ticket_number' => null,
            'date_received_serial' => '46098',
            'time_received_serial' => '0.5833333333',
            'issue_via' => 'via viber call',
            'project_name' => 'Sample Client',
            'issue_description' => 'Set up Access point for starlink',
            'date_resolved_serial' => '46098',
            'time_resolved_serial' => '0.7083333333',
            'resolution' => 'Assist personel for troubleshooting',
            'attended_by' => 'John Arnold Carrasco',
            'requestor_details' => null,
        ], $overrides);

        $columns = [
            ['letter' => 'A', 'header' => 'Ticket Number', 'field' => 'ticket_number', 'type' => 'string'],
            ['letter' => 'B', 'header' => 'Date Received', 'field' => 'date_received_serial', 'type' => 'date'],
            ['letter' => 'C', 'header' => 'Time Received', 'field' => 'time_received_serial', 'type' => 'time'],
            ['letter' => 'D', 'header' => 'Issue / Request Reported via', 'field' => 'issue_via', 'type' => 'string'],
            ['letter' => 'E', 'header' => 'Name of Project', 'field' => 'project_name', 'type' => 'string'],
            ['letter' => 'F', 'header' => 'Issue / Request Description', 'field' => 'issue_description', 'type' => 'string'],
            ['letter' => 'G', 'header' => 'Date Resolved', 'field' => 'date_resolved_serial', 'type' => 'date'],
            ['letter' => 'H', 'header' => 'Time Resolved', 'field' => 'time_resolved_serial', 'type' => 'time'],
            ['letter' => 'I', 'header' => 'Issue / Request Resolution', 'field' => 'resolution', 'type' => 'string'],
            ['letter' => 'J', 'header' => 'Attended by', 'field' => 'attended_by', 'type' => 'string'],
            ['letter' => 'K', 'header' => 'Requestor Details', 'field' => 'requestor_details', 'type' => 'string'],
        ];

        $sharedStrings = [];
        $sharedStringIndex = [];
        $stringIndexFor = function (string $value) use (&$sharedStrings, &$sharedStringIndex): int {
            if (array_key_exists($value, $sharedStringIndex)) {
                return $sharedStringIndex[$value];
            }

            $sharedStringIndex[$value] = count($sharedStrings);
            $sharedStrings[] = $value;

            return $sharedStringIndex[$value];
        };

        $headerCells = [];
        $rowCells = [];

        foreach ($columns as $column) {
            $headerCells[] = sprintf(
                '<c r="%1$s1" t="s"><v>%2$d</v></c>',
                $column['letter'],
                $stringIndexFor($column['header']),
            );

            $value = $row[$column['field']] ?? null;
            if ($value === null || $value === '') {
                continue;
            }

            if ($column['type'] === 'date') {
                $rowCells[] = sprintf('<c r="%s2" s="1"><v>%s</v></c>', $column['letter'], $value);

                continue;
            }

            if ($column['type'] === 'time') {
                $rowCells[] = sprintf('<c r="%s2" s="2"><v>%s</v></c>', $column['letter'], $value);

                continue;
            }

            $rowCells[] = sprintf(
                '<c r="%1$s2" t="s"><v>%2$d</v></c>',
                $column['letter'],
                $stringIndexFor((string) $value),
            );
        }

        $sharedStringsXml = implode('', array_map(
            fn (string $value): string => '<si><t>'.htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8').'</t></si>',
            $sharedStrings,
        ));

        $worksheetXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <sheetData>
    <row r="1">
      %s
    </row>
    <row r="2">
      %s
    </row>
  </sheetData>
</worksheet>
XML;

        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            $this->fail('Unable to create XLSX fixture.');
        }

        try {
            $zip->addFromString('[Content_Types].xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
  <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
  <Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
  <Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>
</Types>
XML);

            $zip->addFromString('_rels/.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>
XML);

            $zip->addFromString('xl/workbook.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <sheets>
    <sheet name="March 2026" sheetId="1" r:id="rId1"/>
  </sheets>
</workbook>
XML);

            $zip->addFromString('xl/_rels/workbook.xml.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
  <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
  <Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>
</Relationships>
XML);

            $zip->addFromString('xl/styles.xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <numFmts count="2">
    <numFmt numFmtId="164" formatCode="mm/dd/yyyy"/>
    <numFmt numFmtId="165" formatCode="[$-409]h:mm:ss\ am/pm"/>
  </numFmts>
  <fonts count="1"><font><sz val="11"/><name val="Calibri"/></font></fonts>
  <fills count="1"><fill><patternFill patternType="none"/></fill></fills>
  <borders count="1"><border/></borders>
  <cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>
  <cellXfs count="3">
    <xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>
    <xf numFmtId="164" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>
    <xf numFmtId="165" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>
  </cellXfs>
</styleSheet>
XML);

            $zip->addFromString('xl/sharedStrings.xml', sprintf(
                '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="%1$d" uniqueCount="%1$d">%2$s</sst>',
                count($sharedStrings),
                $sharedStringsXml,
            ));

            $zip->addFromString('xl/worksheets/sheet1.xml', sprintf(
                $worksheetXml,
                implode('', $headerCells),
                implode('', $rowCells),
            ));
        } finally {
            $zip->close();
        }
    }
}
