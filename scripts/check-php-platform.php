<?php

declare(strict_types=1);

$composerJsonPath = dirname(__DIR__).DIRECTORY_SEPARATOR.'composer.json';

if (! is_file($composerJsonPath)) {
    fwrite(STDERR, "Unable to locate composer.json at {$composerJsonPath}.\n");

    exit(1);
}

$composerJson = json_decode((string) file_get_contents($composerJsonPath), true);
if (! is_array($composerJson)) {
    fwrite(STDERR, "Unable to parse composer.json.\n");

    exit(1);
}

$phpRequirement = $composerJson['require']['php'] ?? null;
if (! is_string($phpRequirement) || trim($phpRequirement) === '') {
    fwrite(STDOUT, "No PHP platform requirement declared in composer.json.\n");

    exit(0);
}

$minimumVersion = null;
if (preg_match('/(\d+\.\d+(?:\.\d+)?)/', $phpRequirement, $matches) === 1) {
    $minimumVersion = $matches[1];
}

if (! is_string($minimumVersion) || $minimumVersion === '') {
    fwrite(STDOUT, "PHP requirement {$phpRequirement} could not be reduced to a minimum version. Skipping preflight.\n");

    exit(0);
}

if (version_compare(PHP_VERSION, $minimumVersion, '>=')) {
    fwrite(STDOUT, sprintf(
        "PHP %s satisfies composer.json require.php (%s).\n",
        PHP_VERSION,
        $phpRequirement
    ));

    exit(0);
}

fwrite(STDERR, sprintf(
    "PHP %s does not satisfy composer.json require.php (%s). Upgrade the runtime before deploying.\n",
    PHP_VERSION,
    $phpRequirement
));

exit(1);
