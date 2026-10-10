<?php

namespace App\Http\Controllers\Concerns;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

trait RendersPdfDownloads
{
    /**
     * @param  array<string, mixed>  $data
     */
    private function downloadA4Pdf(string $view, array $data, string $filename): Response
    {
        return Pdf::loadView($view, $data)
            ->setPaper('a4', 'portrait')
            ->download($filename);
    }
}
