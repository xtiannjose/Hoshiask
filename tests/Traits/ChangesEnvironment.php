<?php

namespace Tests\Traits;

/**
 * Sets environment variables for a test, everywhere env() looks. Laravel's env repository refuses to overwrite
 * values that phpunit.xml set (APP_ENV), so this writes the globals directly and puts everything back afterwards.
 */
trait ChangesEnvironment
{
    /** @var array<string, array{env: mixed, server: mixed, putenv: string|false}> */
    private array $savedEnvironment = [];

    protected function tearDown(): void
    {
        foreach ($this->savedEnvironment as $name => $original) {
            foreach (['_ENV' => 'env', '_SERVER' => 'server'] as $global => $key) {
                if ($original[$key] === null) {
                    unset($GLOBALS[$global][$name]);
                } else {
                    $GLOBALS[$global][$name] = $original[$key];
                }
            }

            $original['putenv'] === false ? putenv($name) : putenv("{$name}={$original['putenv']}");
        }

        $this->savedEnvironment = [];

        parent::tearDown();
    }

    protected function setEnvironmentValue(string $name, ?string $value): void
    {
        $this->savedEnvironment[$name] ??= ['env' => $_ENV[$name] ?? null, 'server' => $_SERVER[$name] ?? null, 'putenv' => getenv($name)];

        if ($value === null) {
            unset($_ENV[$name], $_SERVER[$name]);
            putenv($name);

            return;
        }

        $_ENV[$name] = $_SERVER[$name] = $value;
        putenv("{$name}={$value}");
    }
}
