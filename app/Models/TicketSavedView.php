<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property string $name_normalized
 * @property array<string, string> $filters
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read User $user
 */
class TicketSavedView extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'name_normalized',
        'filters',
    ];

    protected function casts(): array
    {
        return [
            'filters' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $savedView): void {
            $savedView->name_normalized = self::normalizeName($savedView->name);
        });
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function normalizeName(string $name): string
    {
        return Str::limit(Str::lower(Str::squish($name)), 80, '');
    }
}
