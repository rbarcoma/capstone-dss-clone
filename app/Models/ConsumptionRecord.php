<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConsumptionRecord extends Model
{
    protected $fillable = [
        'dataset_id',
        'is_complete',
        'year',
        'month',
        'consumption_kwh',
        'peak_demand_kw',
    ];

    protected function casts(): array
    {
        return ['is_complete' => 'boolean'];
    }
}
