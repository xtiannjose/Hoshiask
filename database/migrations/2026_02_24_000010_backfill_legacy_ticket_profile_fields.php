<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $usersById = DB::table('users')
            ->select('id', 'name', 'phone', 'email')
            ->get()
            ->keyBy('id');

        DB::table('tickets')
            ->select('id', 'user_id', 'name', 'contact_number', 'email', 'province', 'municipality')
            ->orderBy('id')
            ->chunkById(200, function ($tickets) use ($usersById): void {
                foreach ($tickets as $ticket) {
                    $user = $usersById->get($ticket->user_id);
                    $updates = [];

                    if ($this->isBlank($ticket->name)) {
                        $updates['name'] = $this->fallbackValue(optional($user)->name, 'Unknown Requester');
                    }

                    if ($this->isBlank($ticket->contact_number)) {
                        $updates['contact_number'] = $this->fallbackValue(optional($user)->phone, 'N/A');
                    }

                    if ($this->isBlank($ticket->email)) {
                        $updates['email'] = $this->fallbackValue(optional($user)->email, 'unknown@local.invalid');
                    }

                    if ($this->isBlank($ticket->province)) {
                        $updates['province'] = 'Unspecified';
                    }

                    if ($this->isBlank($ticket->municipality)) {
                        $updates['municipality'] = 'Unspecified';
                    }

                    if ($updates !== []) {
                        DB::table('tickets')->where('id', $ticket->id)->update($updates);
                    }
                }
            });
    }

    public function down(): void
    {
        // Data backfill is intentionally irreversible.
    }

    private function isBlank(mixed $value): bool
    {
        return $value === null || trim((string) $value) === '';
    }

    private function fallbackValue(mixed $value, string $fallback): string
    {
        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : $fallback;
    }
};
