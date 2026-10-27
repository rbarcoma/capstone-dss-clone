<?php

namespace App\Support;

use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class AnalyticsData
{
    public static function get(array $filters = []): array
    {
        $actuals = ForecastDataset::actualRecords();
        $periodKey = fn ($row) => sprintf('%04d-%02d', $row->year, $row->month);
        $actualByPeriod = $actuals->keyBy($periodKey);
        $historicalPredictions = collect();
        if ($actuals->isNotEmpty()) {
            try {
                $historicalPredictions = app(MlWorkflow::class)->historicalPredictions();
            } catch (RuntimeException|LockTimeoutException $exception) {
                // Keep valid actuals visible if model training/prediction is unavailable.
                Log::warning('Historical analytics predictions unavailable.', ['reason' => $exception->getMessage()]);
            }
        }
        $forecasts = ForecastDataset::currentForecasts()->orderBy('predicted_at')->orderBy('id')->get()->keyBy($periodKey);
        $periods = $actualByPeriod->keys()->merge($forecasts->keys())->unique()->sort()->values();
        $records = $actuals->filter(fn ($row) => (empty($filters['year']) || $row->year === (int) $filters['year'])
            && (empty($filters['month']) || $row->month === (int) $filters['month'])
        )->values();
        $highest = $actuals->sortByDesc('consumption_kwh')->first();
        $average = fn ($field) => $actuals->isEmpty() ? null : round((float) $actuals->avg($field), 2);

        return [
            'filters' => $filters,
            'records' => $records,
            'monthlyTrend' => $records->map(fn ($row) => [
                'label' => $periodKey($row), 'year' => $row->year, 'month' => $row->month,
                'value' => (float) $row->consumption_kwh,
            ]),
            'actualPredictedTrend' => $periods->map(function ($period) use ($actualByPeriod, $forecasts, $historicalPredictions) {
                $actual = $actualByPeriod->get($period);
                $forecast = $forecasts->get($period);
                $actualValue = $actual ? (float) $actual->consumption_kwh : null;
                $historical = $historicalPredictions->get($period);
                $predictedValue = $forecast ? (float) $forecast->predicted_consumption_kwh
                    : ($actual && $historical ? (float) $historical['predicted'] : null);

                return [
                    'period' => $period, 'label' => $period,
                    'year' => (int) substr($period, 0, 4), 'month' => (int) substr($period, 5, 2),
                    'actual' => $actualValue, 'predicted' => $predictedValue,
                    'accuracy' => $actualValue > 0 && $predictedValue !== null
                        ? round(max(0, 100 - abs($actualValue - $predictedValue) / $actualValue * 100), 2) : null,
                ];
            }),
            'filterOptions' => [
                'years' => $periods->map(fn ($period) => (int) substr($period, 0, 4))->unique()->sort()->values(),
                'months' => range(1, 12),
            ],
            'yearlyComparison' => $actuals->groupBy('year')->sortKeys()->map(fn ($rows, $year) => [
                'year' => $year, 'value' => (float) $rows->avg('consumption_kwh'),
            ])->values(),
            'seasonalPattern' => $actuals->groupBy('month')->sortKeys()->map(fn ($rows, $month) => [
                'month' => $month, 'value' => (float) $rows->avg('consumption_kwh'),
            ])->values(),
            'peakDemand' => $actuals->sortByDesc('peak_demand_kw')->take(12)->map(fn ($row) => [
                'year' => $row->year, 'month' => $row->month, 'value' => (float) $row->peak_demand_kw,
            ])->values(),
            'summary' => [
                'average_consumption' => $average('consumption_kwh'),
                'highest_consumption' => $highest ? (float) $highest->consumption_kwh : null,
                'highest_consumption_year' => $highest?->year,
                'highest_consumption_month' => $highest?->month,
                'average_peak_demand' => $average('peak_demand_kw'),
                'average_solar_irradiance' => $average('solar_irradiance'),
            ],
        ];
    }
}
