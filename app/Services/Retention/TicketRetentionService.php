<?php

namespace App\Services\Retention;

use App\Models\Attachment;
use App\Models\SystemLog;
use App\Models\Ticket;
use App\Models\TicketReply;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Removes the personal details from tickets that were closed or cancelled long ago, and keeps the statistics.
 *
 * What stays: ids, ticket number, status, severity, category, department, province and municipality, ticket
 * type, assignees, every date, the satisfaction rating and the root cause category, so reports and response
 * times keep working. What goes: the requester's name, number and email, the subject and description, every
 * reply's text, the free text typed into closing, cancelling, rating and escalating, the consent IP address and
 * browser, and every attached file.
 */
class TicketRetentionService
{
    public const PLACEHOLDER = '[Removed after the retention period]';

    public const REMOVED_NAME = 'Removed';

    /** Keys in a ticket's audit entries that hold what a person typed. */
    private const FREE_TEXT_LOG_KEYS = ['reason', 'comment', 'close_reason', 'notes', 'message', 'message_before'];

    public function years(): int
    {
        return max(0, (int) config('retention.years', 3));
    }

    public function isEnabled(): bool
    {
        return (bool) config('retention.enabled', false);
    }

    /**
     * Tickets that are past the retention period, not yet cleaned and not on legal hold.
     *
     * @return Builder<Ticket>
     */
    public function eligible(?int $years = null): Builder
    {
        $cutoff = Carbon::now()->subYears($years ?? $this->years());

        return Ticket::query()
            ->whereIn('status', ['closed', 'cancelled'])
            ->whereNull('anonymized_at')
            ->where('retention_hold', false)
            ->whereRaw('COALESCE(closed_at, cancelled_at, resolved_at, updated_at) <= ?', [$cutoff]);
    }

    public function countEligible(?int $years = null): int
    {
        return $this->eligible($years)->count();
    }

    /**
     * Cleans up to $limit eligible tickets (all of them when $limit is null).
     *
     * @param  list<int>  $skipIds  Tickets that failed earlier in this run.
     * @return array{cleaned: list<string>, failed: array<int, string>}
     */
    public function enforce(?int $years = null, ?int $limit = null, array $skipIds = []): array
    {
        $query = $this->eligible($years)->orderBy('id');
        if ($skipIds !== []) {
            $query->whereNotIn('id', $skipIds);
        }
        if ($limit !== null) {
            $query->limit($limit);
        }

        $cleaned = [];
        $failed = [];

        foreach ($query->get() as $ticket) {
            try {
                $this->anonymize($ticket);
                $cleaned[] = $ticket->ticket_number;
            } catch (Throwable $exception) {
                report($exception);
                $failed[$ticket->id] = $ticket->ticket_number;
            }
        }

        return ['cleaned' => $cleaned, 'failed' => $failed];
    }

    /**
     * Files go first. If anything fails the ticket keeps no date in anonymized_at, so the next run takes it again
     * and finishes the job.
     */
    public function anonymize(Ticket $ticket): void
    {
        $this->scrubAttachmentAuditEntries($ticket);
        $this->deleteAttachments($ticket);

        DB::transaction(function () use ($ticket): void {
            // Written with the query builder so the ticket's own updated_at is left as it was.
            DB::table('tickets')->where('id', $ticket->id)->update([
                'name' => self::REMOVED_NAME,
                'contact_number' => null,
                'email' => null,
                'subject' => 'Ticket '.$ticket->ticket_number,
                'description' => self::PLACEHOLDER,
                'satisfaction_comment' => null,
                'root_cause_notes' => null,
                'escalation_reason' => null,
                'cancellation_reason' => null,
                'consent_ip_address' => null,
                'consent_user_agent' => null,
                'anonymized_at' => Carbon::now(),
            ]);

            DB::table('ticket_replies')->where('ticket_id', $ticket->id)->update(['message' => self::PLACEHOLDER]);

            $this->scrubAuditEntries($ticket);
        });
    }

    public function setHold(Ticket $ticket, bool $hold): void
    {
        DB::table('tickets')->where('id', $ticket->id)->update(['retention_hold' => $hold]);
    }

    private function deleteAttachments(Ticket $ticket): void
    {
        $ticket->attachments()->get()->each(fn (Attachment $attachment) => $attachment->delete());

        TicketReply::query()
            ->where('ticket_id', $ticket->id)
            ->with('attachments')
            ->get()
            ->each(fn (TicketReply $reply) => $reply->attachments->each(fn (Attachment $attachment) => $attachment->delete()));
    }

    /**
     * "Downloaded attachment: <file name>" entries name the person's files. Runs before the files are deleted,
     * while their ids are still known; running it again on a retry is harmless.
     */
    private function scrubAttachmentAuditEntries(Ticket $ticket): void
    {
        $attachmentIds = $ticket->attachments()->pluck('id')->merge(
            TicketReply::query()
                ->where('ticket_id', $ticket->id)
                ->with('attachments:id,attachable_id,attachable_type')
                ->get()
                ->flatMap(fn (TicketReply $reply) => $reply->attachments->pluck('id'))
        );

        if ($attachmentIds->isEmpty()) {
            return;
        }

        SystemLog::query()
            ->where('target_type', Attachment::class)
            ->whereIn('target_id', $attachmentIds)
            ->update(['description' => self::PLACEHOLDER]);
    }

    /**
     * A few audit entries about a ticket copy what a person typed (a cancel, reopen or escalation reason).
     */
    private function scrubAuditEntries(Ticket $ticket): void
    {
        SystemLog::query()
            ->where('target_type', Ticket::class)
            ->where('target_id', $ticket->id)
            ->get(['id', 'metadata'])
            ->each(function (SystemLog $entry): void {
                $metadata = $entry->metadata ?? [];
                $changed = false;

                foreach (self::FREE_TEXT_LOG_KEYS as $key) {
                    if (array_key_exists($key, $metadata) && $metadata[$key] !== self::PLACEHOLDER) {
                        $metadata[$key] = self::PLACEHOLDER;
                        $changed = true;
                    }
                }

                if ($changed) {
                    DB::table('system_logs')->where('id', $entry->id)->update(['metadata' => json_encode($metadata)]);
                }
            });
    }
}
