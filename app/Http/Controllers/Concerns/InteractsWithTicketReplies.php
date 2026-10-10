<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Attachment;
use App\Models\Ticket;
use App\Models\TicketReply;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

trait InteractsWithTicketReplies
{
    /**
     * Cap on how many of a ticket's most recent replies are loaded for the initial page render.
     * The live `/replies` polling endpoint is cursor-bounded and unaffected by this cap.
     */
    private const INITIAL_REPLY_LOAD_LIMIT = 200;

    /**
     * @var array<int, array{disk: string, path: string}>
     */
    protected array $trackedAttachmentWrites = [];

    protected function persistAttachmentsFromRequest(Request $request, Ticket|TicketReply $attachable): void
    {
        if (! $request->hasFile('attachments')) {
            return;
        }

        $disk = (string) config('helpdesk.attachments_disk', 'local');

        foreach ($request->file('attachments') as $file) {
            $path = $file->store('attachments', $disk);

            try {
                $attachable->attachments()->create([
                    'filename' => basename($path),
                    'original_filename' => $file->getClientOriginalName(),
                    'file_path' => $path,
                    'mime_type' => $file->getMimeType(),
                    'file_size' => $file->getSize(),
                ]);
            } catch (Throwable $exception) {
                Storage::disk($disk)->delete($path);

                throw $exception;
            }

            $this->trackedAttachmentWrites[] = [
                'disk' => $disk,
                'path' => $path,
            ];
        }
    }

    protected function withAttachmentWriteGuard(callable $callback): mixed
    {
        $baselineCount = count($this->trackedAttachmentWrites);

        try {
            return $callback();
        } catch (Throwable $exception) {
            $this->deleteTrackedAttachmentWrites(array_slice($this->trackedAttachmentWrites, $baselineCount));

            throw $exception;
        } finally {
            $this->trackedAttachmentWrites = array_slice($this->trackedAttachmentWrites, 0, $baselineCount);
        }
    }

    protected function formatReplyForChat(TicketReply $reply): array
    {
        $viewer = $this->currentReplyViewer();
        $fromSupport = in_array(optional($reply->user)->normalizedRole(), User::TICKET_CONSOLE_ROLES, true);
        $createdAt = $reply->created_at;
        $replyTo = $reply->replyTo && $reply->replyTo->isVisibleTo($viewer)
            ? $reply->replyTo
            : null;

        return [
            'id' => $reply->id,
            'message' => $reply->message,
            'is_internal' => (bool) $reply->is_internal,
            'created_at_iso' => optional($createdAt)->toIso8601String(),
            'from_support' => $fromSupport,
            'avatar_logo' => $this->departmentLogoForUser($reply->user, $fromSupport),
            'can_manage' => (bool) ($reply->user_id === auth()->id()),
            'edited' => (bool) $reply->edited_at,
            'deleted' => (bool) $reply->deleted_at,
            'reply_to_id' => $reply->reply_to_id,
            'reply_to_text' => $replyTo ? Str::limit($replyTo->message, 120) : null,
            'attachments' => $reply->deleted_at ? collect() : $reply->attachments->map(function (Attachment $attachment) {
                $previewUrl = $attachment->preview_url;

                return [
                    'download_url' => $attachment->download_url,
                    'preview_url' => $previewUrl,
                    'original_filename' => $attachment->original_filename,
                    'mime_type' => $attachment->mime_type,
                    'is_image' => str_starts_with((string) $attachment->mime_type, 'image/'),
                    'can_preview' => (bool) $previewUrl,
                ];
            })->values(),
        ];
    }

    protected function replyTargetExistsForTicket(Ticket $ticket, ?int $replyToId, bool $includeInternal = true): bool
    {
        if (! $replyToId) {
            return true;
        }

        return $this->visibleRepliesQueryForTicket($ticket, $includeInternal)
            ->whereKey($replyToId)
            ->exists();
    }

    /**
     * Shared chat-reply edit for both portals. Callers authorize access to the ticket
     * first; the client portal also limits edits to TicketReply::editWindowMinutes().
     */
    protected function editReplyMessage(Request $request, Ticket $ticket, TicketReply $reply, bool $enforceEditWindow): JsonResponse
    {
        $this->authorizeReplyOnTicket($ticket, $reply, 'update');

        if ($reply->deleted_at) {
            return response()->json(['message' => 'Deleted messages cannot be edited.'], 422);
        }

        if ($enforceEditWindow && ! $reply->isWithinEditWindow()) {
            return response()->json(['message' => 'Messages can only be edited within '.TicketReply::editWindowLabel().'.'], 422);
        }

        $previousMessage = (string) $reply->message;
        $newMessage = $request->string('message')->toString();

        $reply->update([
            'message' => $newMessage,
            'edited_at' => now(),
        ]);

        $this->recordReplyAudit(
            $request,
            $ticket,
            $reply,
            'ticket.reply.updated',
            'Edited a ticket reply.',
            $previousMessage !== $newMessage ? ['message_before' => $previousMessage] : []
        );

        return response()->json([
            'message' => 'Message edited',
            'reply' => $this->formatReplyForChat($reply->fresh(['user', 'attachments', 'replyTo.user'])),
        ]);
    }

    /**
     * Shared chat-reply delete (the text is replaced, the row is kept for the thread).
     */
    protected function softDeleteReply(Request $request, Ticket $ticket, TicketReply $reply, bool $enforceEditWindow): JsonResponse
    {
        $this->authorizeReplyOnTicket($ticket, $reply, 'delete');

        if ($enforceEditWindow && ! $reply->isWithinEditWindow()) {
            return response()->json(['message' => 'Messages can only be deleted within '.TicketReply::editWindowLabel().'.'], 422);
        }

        if (! $reply->deleted_at) {
            $previousMessage = (string) $reply->message;

            $reply->update([
                'message' => 'This message was deleted.',
                'deleted_at' => now(),
            ]);

            $this->recordReplyAudit($request, $ticket, $reply, 'ticket.reply.deleted', 'Deleted a ticket reply.', ['message_before' => $previousMessage]);
        }

        return response()->json([
            'message' => 'Message deleted',
            'reply' => $this->formatReplyForChat($reply->fresh(['user', 'attachments', 'replyTo.user'])),
        ]);
    }

    private function authorizeReplyOnTicket(Ticket $ticket, TicketReply $reply, string $ability): void
    {
        if ($reply->ticket_id !== $ticket->id) {
            abort(403);
        }

        $this->authorize($ability, $reply);
    }

    /**
     * @param  array<string, string>  $extra  `message_before` holds the text an edit or delete replaced. It is kept only
     *                                        here, in the shadow account's System Logs, and never shown in a conversation.
     *                                        Retention clean-up blanks it with the rest of the ticket's typed text.
     */
    private function recordReplyAudit(Request $request, Ticket $ticket, TicketReply $reply, string $event, string $description, array $extra = []): void
    {
        $this->systemLogs->record($event, $description, [
            'category' => 'ticket',
            'target_type' => Ticket::class,
            'target_id' => $ticket->id,
            'metadata' => [
                'ticket_number' => $ticket->ticket_number,
                'reply_id' => $reply->id,
            ] + array_map(static fn (string $text): string => Str::limit($text, 5000, '...'), $extra),
            'request' => $request,
        ]);
    }

    protected function departmentLogoForUser(?User $user, bool $fromSupport): string
    {
        if ($fromSupport && ! $user) {
            return User::supportLogoUrl();
        }

        return User::departmentBrandAssets(optional($user)->department, optional($user)->role)['logo_url'];
    }

    protected function currentReplyViewer(): ?User
    {
        return auth()->user();
    }

    protected function visibleRepliesQueryForTicket(Ticket $ticket, bool $includeInternal = true): HasMany|Builder
    {
        return $ticket->replies()
            ->when(! $includeInternal, fn (Builder $query) => $query->where('is_internal', false))
            ->visibleToViewer($this->currentReplyViewer());
    }

    protected function visibleRepliesRelationForTicket(Ticket $ticket, bool $includeInternal = true, bool $all = false): Collection
    {
        $query = $this->replyFeedQueryForTicket($ticket, $includeInternal)->latest('created_at');

        if (! $all) {
            $query->take(self::INITIAL_REPLY_LOAD_LIMIT);
        }

        return $query->get()->sortBy('created_at')->values();
    }

    /**
     * Loads the ticket page's data. Only the newest replies are rendered unless $all is set; the return value is how
     * many older visible replies were left out (0 when nothing was), so the page can say so and offer the rest.
     */
    protected function loadTicketWithVisibleReplies(Ticket $ticket, bool $includeInternal = true, bool $all = false): int
    {
        $ticket->load(['user', 'createdByUser', 'category', 'assignedUser', 'assignedUsers', 'closedBy', 'attachments']);
        $replies = $this->visibleRepliesRelationForTicket($ticket, $includeInternal, $all);
        $ticket->setRelation('replies', $replies);

        if ($all || $replies->count() < self::INITIAL_REPLY_LOAD_LIMIT) {
            return 0;
        }

        return max(0, $this->visibleRepliesQueryForTicket($ticket, $includeInternal)->count() - $replies->count());
    }

    protected function replyFeedResponseForTicket(Request $request, Ticket $ticket, bool $includeInternal = true): JsonResponse
    {
        $replyFeedCursor = $this->normalizedReplyFeedCursor($request);
        $replyFeedQuery = $this->replyFeedQueryForTicket($ticket, $includeInternal);
        $latestVisibleReplyUpdatedAt = (clone $this->visibleRepliesQueryForTicket($ticket, $includeInternal))->max('updated_at');
        $latestReplyCursor = $latestVisibleReplyUpdatedAt
            ? $this->normalizedReplyFeedCursorValue(Carbon::parse((string) $latestVisibleReplyUpdatedAt))
            : ($replyFeedCursor ? $this->normalizedReplyFeedCursorValue($replyFeedCursor) : '');

        if ($replyFeedCursor) {
            if ($latestVisibleReplyUpdatedAt) {
                $latestVisibleReplyUpdatedAt = Carbon::parse((string) $latestVisibleReplyUpdatedAt);
            }

            if (! $latestVisibleReplyUpdatedAt || $latestVisibleReplyUpdatedAt->gte($replyFeedCursor)) {
                $replyFeedQuery->where('updated_at', '>=', $replyFeedCursor->copy()->subSecond());
            } else {
                return response()->json([
                    'replies' => [],
                    'cursor' => $latestReplyCursor,
                ]);
            }
        }

        /** @var Collection<int, TicketReply> $replyModels */
        $replyModels = $replyFeedQuery
            ->orderBy('created_at')
            ->get();

        $replies = $replyModels
            ->map(fn (TicketReply $reply) => $this->formatReplyForChat($reply))
            ->values();

        return response()->json([
            'replies' => $replies,
            'cursor' => $latestReplyCursor,
        ]);
    }

    protected function replyFeedCursorForReplies(Collection $replies): string
    {
        /** @var TicketReply|null $latestReply */
        $latestReply = $replies
            ->filter(fn ($reply) => $reply instanceof TicketReply && $reply->updated_at)
            ->sortBy(fn (TicketReply $reply) => $reply->updated_at?->getTimestamp() ?? 0)
            ->last();

        return $latestReply?->updated_at
            ? $this->normalizedReplyFeedCursorValue($latestReply->updated_at)
            : '';
    }

    protected function replyFeedQueryForTicket(Ticket $ticket, bool $includeInternal = true): HasMany|Builder
    {
        return $this->visibleRepliesQueryForTicket($ticket, $includeInternal)
            ->with([
                'user',
                'attachments',
                'replyTo' => function ($query) {
                    /** @var BelongsTo<TicketReply, TicketReply> $query */
                    return $query
                        ->visibleToViewer($this->currentReplyViewer())
                        ->with('user');
                },
            ]);
    }

    private function normalizedReplyFeedCursor(Request $request): ?Carbon
    {
        $updatedAfter = trim($request->string('updated_after')->toString());
        if ($updatedAfter === '') {
            return null;
        }

        try {
            return Carbon::parse($updatedAfter)->setTimezone((string) config('app.timezone'));
        } catch (Throwable) {
            return null;
        }
    }

    private function normalizedReplyFeedCursorValue(Carbon $timestamp): string
    {
        return $timestamp->copy()->utc()->toIso8601String();
    }

    /**
     * @param  array<int, array{disk: string, path: string}>  $writes
     */
    private function deleteTrackedAttachmentWrites(array $writes): void
    {
        foreach (array_reverse($writes) as $write) {
            Storage::disk($write['disk'])->delete($write['path']);
        }
    }
}
