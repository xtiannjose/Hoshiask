<?php

namespace App\Http\Requests\Concerns;

use App\Support\UploadLimits;
use Illuminate\Http\UploadedFile;

/**
 * Plain-language attachment errors that name the file and say what to do, instead of
 * "The attachments.0 failed to upload."
 */
trait HasReadableAttachmentMessages
{
    /**
     * @return array<string, string>
     */
    protected function attachmentMessages(): array
    {
        $limit = UploadLimits::perFileLabel();
        $acceptedTypes = 'Use JPG, PNG, PDF, DOCX, XLSX or TXT. Save an older .doc or .xls file as .docx or .xlsx first.';

        return [
            'attachments.*.max' => ':attribute is too large. Each file can be at most '.$limit.'.',
            'attachments.*.uploaded' => 'The server could not receive :attribute. It is probably larger than the limit of '.$limit.' per file, so please choose a smaller one.',
            'attachments.*.mimes' => ':attribute is not an accepted file type. '.$acceptedTypes,
            'attachments.*.mimetypes' => ':attribute is not an accepted file type. '.$acceptedTypes,
            'attachments.*.file' => ':attribute could not be uploaded. Please choose it again.',
            'attachments.required' => 'Please attach at least one file.',
            'attachments.min' => 'Please attach at least one file.',
            'attachments.array' => 'Please choose your files again.',
            'attachments.max' => 'You can attach at most '.UploadLimits::MAX_FILES.' files at a time. Send the rest in a reply.',
        ];
    }

    /**
     * Names each uploaded file in its error message.
     *
     * @return array<string, string>
     */
    protected function attachmentAttributes(): array
    {
        $files = $this->file('attachments');
        if (! is_array($files)) {
            return [];
        }

        $attributes = [];
        foreach ($files as $index => $file) {
            if ($file instanceof UploadedFile) {
                $name = trim((string) preg_replace('/[[:cntrl:]]+/u', ' ', $file->getClientOriginalName()));
                $attributes['attachments.'.$index] = '"'.($name !== '' ? $name : 'File '.((int) $index + 1)).'"';
            }
        }

        return $attributes;
    }
}
