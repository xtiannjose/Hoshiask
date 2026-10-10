<?php

namespace App\Models;

use App\Support\LikePattern;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CannedResponse extends Model
{
    protected $fillable = [
        'created_by_user_id',
        'title',
        'body',
    ];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function scopeSearch(Builder $query, string $term): Builder
    {
        $like = LikePattern::contains(mb_strtolower(trim($term)));

        // Grouped so the OR cannot escape any other conditions on the query.
        return $query->where(function (Builder $builder) use ($like) {
            $builder->whereRaw('LOWER(title) LIKE ? '.LikePattern::LIKE_ESCAPE, [$like])
                ->orWhereRaw('LOWER(body) LIKE ? '.LikePattern::LIKE_ESCAPE, [$like]);
        });
    }
}
