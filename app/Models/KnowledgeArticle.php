<?php

namespace App\Models;

use App\Enums\KnowledgeArticleCategory;
use App\Models\Concerns\GeneratesUniqueSlug;
use App\Support\LikePattern;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KnowledgeArticle extends Model
{
    use GeneratesUniqueSlug;
    use HasFactory;

    protected $fillable = [
        'created_by_user_id',
        'category',
        'title',
        'slug',
        'body',
        'is_published',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    public function scopeSearch(Builder $query, string $term): Builder
    {
        $like = LikePattern::contains(mb_strtolower(trim($term)));

        return $query->where(function (Builder $builder) use ($like) {
            $builder->whereRaw('LOWER(title) LIKE ? '.LikePattern::LIKE_ESCAPE, [$like])
                ->orWhereRaw('LOWER(body) LIKE ? '.LikePattern::LIKE_ESCAPE, [$like]);
        });
    }

    public function getCategoryLabelAttribute(): string
    {
        return KnowledgeArticleCategory::tryFrom($this->category)?->label() ?? ucfirst($this->category);
    }

    public static function generateAvailableSlug(string $title, ?int $ignoreArticleId = null): string
    {
        return self::uniqueSlugFor($title, 'article', $ignoreArticleId);
    }
}
