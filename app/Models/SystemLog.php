<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $actor_user_id
 * @property string $category
 * @property string $event_type
 * @property string|null $target_type
 * @property int|null $target_id
 * @property string $description
 * @property array<string, mixed>|null $metadata
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property Carbon $occurred_at
 * @property-read User|null $actor
 */
class SystemLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'actor_user_id',
        'category',
        'event_type',
        'target_type',
        'target_id',
        'description',
        'metadata',
        'ip_address',
        'user_agent',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
