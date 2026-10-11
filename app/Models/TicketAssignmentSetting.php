<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TicketAssignmentSetting extends Model
{
    public const METHOD_WEIGHTED_RANDOM = 'weighted_random';

    public const METHOD_ROTATING_COUNTER = 'rotating_counter';

    public const METHOD_LEAST_WORKLOAD = 'least_workload';

    public const METHODS = [
        self::METHOD_WEIGHTED_RANDOM,
        self::METHOD_ROTATING_COUNTER,
        self::METHOD_LEAST_WORKLOAD,
    ];

    protected $fillable = [
        'distribution_method',
    ];

    public static function current(): self
    {
        return static::query()->firstOrCreate([], ['distribution_method' => self::METHOD_WEIGHTED_RANDOM]);
    }
}
