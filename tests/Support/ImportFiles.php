<?php

namespace Tests\Support;

/**
 * Where the import tests put the files they feed to `tickets:import`. A temporary folder, never the application's
 * real storage/app/private/imports: a test file with the same name as a real import file would overwrite it.
 *
 * Tests.TestCase points the import disk at the same folder, so the command finds a bare file name there.
 */
final class ImportFiles
{
    /** The root of the "import-tests" disk. */
    public static function root(): string
    {
        return sys_get_temp_dir().DIRECTORY_SEPARATOR.'helpdesk-import-tests';
    }

    public static function path(string $name): string
    {
        $directory = self::root().DIRECTORY_SEPARATOR.'imports';

        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        return $directory.DIRECTORY_SEPARATOR.$name;
    }
}
