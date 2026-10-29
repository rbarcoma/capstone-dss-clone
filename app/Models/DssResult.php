<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DssResult extends Model
{
    protected static function booted(): void
    {
        static::addGlobalScope('current', fn (Builder $query) => $query->whereNull('dss_results.archived_at'));
    }

    protected $fillable = [
        'forecast_year',
        'forecast_month',
        'input_hash',
        'updated_at',
        'user_id',
        'forecast_result_id',
        'demand_status',
        'readiness_level',
        'recommendations',
        'priority_actions',
        'basis',
    ];

    protected function casts(): array
    {
        return [
            'recommendations' => 'array',
            'priority_actions' => 'array',
            'basis' => 'array',
            'archived_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function forecastResult(): BelongsTo
    {
        return $this->belongsTo(ForecastResult::class);
    }
}
