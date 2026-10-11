<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketAssignmentRule extends Model
{
    protected $fillable = [
        'category_id',
        'user_id',
        'weight',
    ];

    protected function casts(): array
    {
        return [
            'weight' => 'integer',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
