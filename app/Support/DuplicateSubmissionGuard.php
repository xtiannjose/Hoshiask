<?php

namespace App\Support;

use App\Models\Ticket;
use App\Models\TicketReply;
use Closure;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;

/**
 * Keeps one new-ticket form from making two tickets.
 *
 * A double click, the back button followed by Submit, a refresh that resends the form or a retry on a weak
 * connection all send the same form again. Within ten minutes a repeat from the same person with the same content
 * gets the first ticket back instead of creating another one. A repeat that arrives while the first request is still
 * working waits for it, so even two requests at the same moment make one ticket.
 *
 * A reply works the same way over a shorter window (30 seconds): the same person sending the same words and files
 * to the same ticket gets the reply already saved, so a double click does not post it, or email it, twice.
 */
final class DuplicateSubmissionGuard
{
    public const REMEMBER_MINUTES = 10;

    /** Short, so that someone who really means to send the same words again a little later can. */
    public const REPLY_REMEMBER_SECONDS = 30;

    /** The longest a ticket may take to create before another request is allowed to wait on it. */
    private const LOCK_SECONDS = 60;

    /** How long a repeat waits for the first request to finish. Tests lower it. */
    public static int $waitSeconds = 20;

    /**
     * Creates the ticket unless the same person already sent the same content a moment ago.
     *
     * @param  Closure(): Ticket  $create
     * @return array{ticket: Ticket, repeat: bool}
     *
     * @throws LockTimeoutException when the first request is still running after the wait
     */
    public static function once(string $fingerprint, Closure $create): array
    {
        $result = self::rememberOnce('ticket-submission:'.$fingerprint, Ticket::class, $create, self::REMEMBER_MINUTES * 60);
        $ticket = $result['model'];
        assert($ticket instanceof Ticket);

        return ['ticket' => $ticket, 'repeat' => $result['repeat']];
    }

    /**
     * Saves the reply unless the same person sent the same content to the same ticket a moment ago.
     *
     * @param  Closure(): TicketReply  $create
     * @return array{reply: TicketReply, repeat: bool}
     *
     * @throws LockTimeoutException when the first request is still running after the wait
     */
    public static function onceReply(string $fingerprint, Closure $create): array
    {
        $result = self::rememberOnce('reply-submission:'.$fingerprint, TicketReply::class, $create, self::REPLY_REMEMBER_SECONDS);
        $reply = $result['model'];
        assert($reply instanceof TicketReply);

        return ['reply' => $reply, 'repeat' => $result['repeat']];
    }

    /**
     * @param  class-string<Model>  $modelClass
     * @param  Closure(): Model  $create
     * @return array{model: Model, repeat: bool}
     */
    private static function rememberOnce(string $key, string $modelClass, Closure $create, int $seconds): array
    {
        $lock = Cache::lock($key.':lock', self::LOCK_SECONDS);
        $lock->block(self::$waitSeconds);

        try {
            $rememberedId = Cache::get($key);
            $earlier = $rememberedId !== null ? $modelClass::query()->find($rememberedId) : null;
            if ($earlier instanceof $modelClass) {
                return ['model' => $earlier, 'repeat' => true];
            }

            $model = $create();
            Cache::put($key, $model->getKey(), now()->addSeconds($seconds));

            return ['model' => $model, 'repeat' => false];
        } finally {
            $lock->release();
        }
    }

    /**
     * Identifies "this person sent this content": the person, which form, every submitted field (leading and
     * trailing spaces and runs of spaces ignored) and the names and sizes of the attached files.
     */
    public static function fingerprint(string $form, int $actorId, Request $request): string
    {
        $fields = self::normalise($request->except(['_token', '_method', 'attachments']));
        ksort($fields);

        $files = collect($request->file('attachments', []))
            ->filter(fn (mixed $file): bool => $file instanceof UploadedFile)
            ->map(fn (UploadedFile $file): string => $file->getClientOriginalName().'|'.$file->getSize())
            ->sort()
            ->values()
            ->all();

        return hash('sha256', json_encode([$form, $actorId, $fields, $files], JSON_THROW_ON_ERROR));
    }

    /**
     * @param  array<mixed>  $values
     * @return array<mixed>
     */
    private static function normalise(array $values): array
    {
        return array_map(
            fn (mixed $value): mixed => match (true) {
                is_array($value) => self::normalise($value),
                is_string($value) => trim((string) preg_replace('/\s+/u', ' ', $value)),
                default => $value,
            },
            $values
        );
    }
}
