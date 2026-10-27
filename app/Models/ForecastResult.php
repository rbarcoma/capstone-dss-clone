<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ForecastResult extends Model
{
    protected $fillable = [
        'user_id',
        'dataset_hash',
        'predicted_at',
        'year',
        'month',
        'predicted_consumption_kwh',
        'previous_consumption_kwh',
        'change_percent',
        'mae',
        'rmse',
        'r2_score',
        'model_type',
    ];

    protected function casts(): array
    {
        return [
            'predicted_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
