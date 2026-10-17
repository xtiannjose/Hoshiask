<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Admin\SignInActivityService;
use App\Services\SystemLogService;
use Illuminate\Console\Command;

class HoshiaskUnlockLogin extends Command
{
    protected $signature = 'hoshiask:unlock-login {login : The username or email address that is locked out}';

    protected $description = 'Lift the sign-in lockout of a login name from every address, for example when the shadow account itself is locked.';

    public function handle(SignInActivityService $signIns, SystemLogService $systemLogs): int
    {
        $login = trim((string) $this->argument('login'));

        if (! $signIns->clearLockout($login)) {
            $this->info("\"{$login}\" is not locked out.");

            return self::SUCCESS;
        }

        $account = $signIns->accountForLogin($login);
        $systemLogs->record('auth.lockout_cleared', 'Cleared a sign-in lockout.', [
            'category' => 'security',
            'target_type' => $account ? User::class : null,
            'target_id' => $account?->id,
            'metadata' => ['login' => $login, 'source' => 'command'],
        ]);

        $this->info("Unlocked \"{$login}\". It can sign in again now.");

        return self::SUCCESS;
    }
}
