<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HidesForbiddenRecordsFromClients;
use App\Models\Attachment;
use App\Services\SystemLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\HeaderUtils;

class AttachmentController extends Controller
{
    use HidesForbiddenRecordsFromClients;

    /** A preview is written to the audit trail at most once in this time for the same person and file. */
    private const PREVIEW_LOG_WINDOW_MINUTES = 60;

    public function __construct(private SystemLogService $systemLogs) {}

    public function download(Request $request, Attachment $attachment)
    {
        $this->authorizeHidingFromClients('download', $attachment);

        $previewAllowedMimeTypes = Attachment::previewableMimeTypes();
        $storageDisk = $attachment->resolvedDisk();
        if (! Storage::disk($storageDisk)->exists($attachment->file_path)) {
            abort(404);
        }

        if ($request->boolean('preview')) {
            $storedMimeType = strtolower((string) $attachment->mime_type);
            if ($storedMimeType !== '' && ! in_array($storedMimeType, $previewAllowedMimeTypes, true)) {
                abort(415, 'Preview is not supported for this file type.');
            }

            $detectedMimeType = strtolower((string) (Storage::disk($storageDisk)->mimeType($attachment->file_path) ?: ''));
            $previewMimeType = $detectedMimeType !== '' ? $detectedMimeType : $storedMimeType;
            if ($previewMimeType === '' || ! in_array($previewMimeType, $previewAllowedMimeTypes, true)) {
                abort(415, 'Preview is not supported for this file type.');
            }

            $stream = Storage::disk($storageDisk)->readStream($attachment->file_path);
            if (! is_resource($stream)) {
                abort(404);
            }

            $this->recordAccess($request, $attachment);

            $downloadName = (string) ($attachment->original_filename ?: $attachment->filename ?: 'attachment');
            try {
                $contentDisposition = HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_INLINE, $downloadName);
            } catch (\Throwable) {
                $fallbackName = preg_replace('/[^A-Za-z0-9._-]/', '_', $downloadName) ?: 'attachment';
                $contentDisposition = 'inline; filename="'.$fallbackName.'"';
            }

            return response()->stream(function () use ($stream) {
                fpassthru($stream);
                if (is_resource($stream)) {
                    fclose($stream);
                }
            }, 200, [
                'Content-Disposition' => $contentDisposition,
                'Content-Type' => $previewMimeType,
                'X-Content-Type-Options' => 'nosniff',
            ]);
        }

        $this->recordAccess($request, $attachment);

        return Storage::disk($storageDisk)->download($attachment->file_path, $attachment->original_filename);
    }

    /**
     * Written only once the file is really being sent, so a missing file or a refused preview is not recorded
     * as a download.
     *
     * A preview shows the file inside the ticket page, so a page with ten images would write ten rows every time it
     * is opened. A preview is written at most once an hour for the same person and file, which still answers "who saw
     * this file" without filling the audit trail. A real download is always written.
     */
    private function recordAccess(Request $request, Attachment $attachment): void
    {
        if ($request->boolean('preview')) {
            // add() stores the key only when it is not there yet, so it is true for the first preview in the window.
            $firstInTheWindow = Cache::add(
                'attachment-preview-logged:'.(int) auth()->id().':'.$attachment->id,
                true,
                now()->addMinutes(self::PREVIEW_LOG_WINDOW_MINUTES)
            );

            if (! $firstInTheWindow) {
                return;
            }
        }

        $this->systemLogs->record(
            'attachment.downloaded',
            'Downloaded attachment: '.($attachment->original_filename ?? $attachment->filename ?? 'unknown'),
            [
                'category' => 'ticket',
                'target_type' => Attachment::class,
                'target_id' => $attachment->id,
                'metadata' => ['preview' => $request->boolean('preview')],
                'request' => $request,
            ]
        );
    }
}
