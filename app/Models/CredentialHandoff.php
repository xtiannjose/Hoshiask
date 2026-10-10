<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CredentialHandoff extends Model
{
    use HasFactory;

    protected $fillable = [
        'target_user_id',
        'issued_by_user_id',
        'temporary_password',
        'expires_at',
        'revealed_at',
        'consumed_at',
    ];

    protected function casts(): array
    {
        return [
            'temporary_password' => 'encrypted',
            'expires_at' => 'datetime',
            'revealed_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }

    public function targetUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }
}
