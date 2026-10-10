<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('attachments')) {
            return;
        }

        $targetDisk = (string) config('helpdesk.attachments_disk', 'local');
        $sourceDisk = 'public';

        DB::table('attachments')
            ->select(['id', 'file_path'])
            ->orderBy('id')
            ->cursor()
            ->each(function (object $attachment) use ($targetDisk, $sourceDisk): void {
                $path = trim((string) $attachment->file_path);
                if ($path === '') {
                    return;
                }

                if (! Storage::disk($sourceDisk)->exists($path)) {
                    return;
                }

                if (! Storage::disk($targetDisk)->exists($path)) {
                    $stream = Storage::disk($sourceDisk)->readStream($path);
                    if (is_resource($stream)) {
                        Storage::disk($targetDisk)->writeStream($path, $stream);
                        fclose($stream);
                    } else {
                        Storage::disk($targetDisk)->put($path, Storage::disk($sourceDisk)->get($path));
                    }
                }

                Storage::disk($sourceDisk)->delete($path);
            });
    }

    public function down(): void
    {
        if (! Schema::hasTable('attachments')) {
            return;
        }

        $sourceDisk = (string) config('helpdesk.attachments_disk', 'local');
        $targetDisk = 'public';

        DB::table('attachments')
            ->select(['id', 'file_path'])
            ->orderBy('id')
            ->cursor()
            ->each(function (object $attachment) use ($sourceDisk, $targetDisk): void {
                $path = trim((string) $attachment->file_path);
                if ($path === '') {
                    return;
                }

                if (! Storage::disk($sourceDisk)->exists($path)) {
                    return;
                }

                if (! Storage::disk($targetDisk)->exists($path)) {
                    $stream = Storage::disk($sourceDisk)->readStream($path);
                    if (is_resource($stream)) {
                        Storage::disk($targetDisk)->writeStream($path, $stream);
                        fclose($stream);
                    } else {
                        Storage::disk($targetDisk)->put($path, Storage::disk($sourceDisk)->get($path));
                    }
                }
            });
    }
};
