<?php

namespace Tests\Feature;

use Tests\TestCase;

class SessionSecureCookieConfigTest extends TestCase
{
    /** @var array<string, array{env: mixed, server: mixed, putenv: string|false}> */
    private array $saved = [];

    protected function tearDown(): void
    {
        foreach ($this->saved as $name => $original) {
            $this->restore($name, $original);
        }

        parent::tearDown();
    }

    /**
     * Sets a variable everywhere env() looks (the repository refuses to overwrite values that phpunit.xml set).
     */
    private function setEnv(string $name, ?string $value): void
    {
        $this->saved[$name] ??= ['env' => $_ENV[$name] ?? null, 'server' => $_SERVER[$name] ?? null, 'putenv' => getenv($name)];

        if ($value === null) {
            unset($_ENV[$name], $_SERVER[$name]);
            putenv($name);

            return;
        }

        $_ENV[$name] = $_SERVER[$name] = $value;
        putenv("{$name}={$value}");
    }

    /**
     * @param  array{env: mixed, server: mixed, putenv: string|false}  $original
     */
    private function restore(string $name, array $original): void
    {
        foreach (['_ENV' => 'env', '_SERVER' => 'server'] as $global => $key) {
            if ($original[$key] === null) {
                unset($GLOBALS[$global][$name]);
            } else {
                $GLOBALS[$global][$name] = $original[$key];
            }
        }

        $original['putenv'] === false ? putenv($name) : putenv("{$name}={$original['putenv']}");
    }

    private function secureFlag(?string $secureCookie, string $appEnv): bool
    {
        $this->setEnv('SESSION_SECURE_COOKIE', $secureCookie);
        $this->setEnv('APP_ENV', $appEnv);

        $config = require config_path('session.php');

        return $config['secure'];
    }

    public function test_a_blank_setting_follows_the_environment(): void
    {
        $this->assertTrue($this->secureFlag('', 'production'));
        $this->assertFalse($this->secureFlag('', 'local'));
        $this->assertTrue($this->secureFlag(null, 'production'));
    }

    public function test_an_explicit_setting_wins_over_the_environment(): void
    {
        $this->assertTrue($this->secureFlag('true', 'local'));
        $this->assertFalse($this->secureFlag('false', 'production'));
    }
}
