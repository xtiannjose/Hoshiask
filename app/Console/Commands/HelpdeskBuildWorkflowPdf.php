<?php

namespace App\Console\Commands;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use League\CommonMark\GithubFlavoredMarkdownConverter;

class HelpdeskBuildWorkflowPdf extends Command
{
    protected $signature = 'helpdesk:build-workflow-pdf';

    protected $description = 'Regenerate docs/ticket-system-workflow.pdf from the current docs/ticket-system-workflow.md.';

    public function handle(): int
    {
        $markdownPath = base_path('docs/ticket-system-workflow.md');
        $pdfPath = base_path('docs/ticket-system-workflow.pdf');

        if (! File::exists($markdownPath)) {
            $this->error("Markdown source not found: {$markdownPath}");

            return self::FAILURE;
        }

        $bodyHtml = (string) (new GithubFlavoredMarkdownConverter)->convert(File::get($markdownPath));

        $html = view('docs.workflow-pdf', [
            'bodyHtml' => $bodyHtml,
            'generatedAt' => now(),
        ])->render();

        Pdf::loadHTML($html)->setPaper('a4', 'portrait')->save($pdfPath);

        $this->info("Regenerated {$pdfPath}");

        return self::SUCCESS;
    }
}
