<?php

namespace App\Console\Commands;

use App\Models\SystemLog;
use App\Services\SystemLogService;
use App\Support\LoggedLoginText;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class HelpdeskScrubFailedLoginText extends Command
{
    protected $signature = 'helpdesk:scrub-failed-login-text
        {--dry-run : Show how many entries would change without changing anything}';

    protected $description = 'Mask text typed into the sign-in box in old failed sign-in log entries, unless it names a real account.';

    public function handle(SystemLogService $systemLogs): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $accounts = $this->knownLogins();
        $changed = 0;

        SystemLog::query()
            ->where('event_type', 'auth.login_failed')
            ->chunkById(200, function ($entries) use ($accounts, $dryRun, &$changed): void {
                foreach ($entries as $entry) {
                    $typed = trim((string) ($entry->metadata['login'] ?? ''));

                    if ($typed === '' || LoggedLoginText::isMasked($typed)) {
                        continue;
                    }

                    $kept = LoggedLoginText::forLog($typed, isset($accounts[mb_strtolower($typed)]));
                    if ($kept === $typed) {
                        continue;
                    }

                    $changed++;

                    if (! $dryRun) {
                        // Written directly so the entry's timestamps stay as they were.
                        DB::table('system_logs')
                            ->where('id', $entry->id)
                            ->update(['metadata' => json_encode([...$entry->metadata, 'login' => $kept])]);
                    }
                }
            });

        $entries = Str::plural('failed sign-in entry', $changed);

        if ($dryRun) {
            $this->info("Dry run: {$changed} {$entries} would be masked. Nothing was changed.");

            return self::SUCCESS;
        }

        if ($changed > 0) {
            $systemLogs->record('system.scrub_failed_login_text', 'Masked typed sign-in text in old failed sign-in entries.', [
                'category' => 'system',
                'metadata' => ['entries_changed' => $changed],
            ]);
        }

        $this->info("Masked the typed text in {$changed} {$entries}.");

        return self::SUCCESS;
    }

    /**
     * Every username and email address of a real account, lower case, as array keys.
     *
     * @return array<string, int>
     */
    private function knownLogins(): array
    {
        $known = [];

        foreach (DB::table('users')->select(['username', 'email'])->get() as $account) {
            foreach ([$account->username, $account->email] as $login) {
                if (is_string($login) && trim($login) !== '') {
                    $known[mb_strtolower(trim($login))] = 1;
                }
            }
        }

        return $known;
    }
}
