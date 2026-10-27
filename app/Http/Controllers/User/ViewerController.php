<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\DssResult;
use App\Models\ForecastResult;
use App\Models\ProcessedRecord;
use App\Models\Report;
use App\Support\AnalyticsData;
use App\Support\ForecastDataset;
use Inertia\Inertia;

class ViewerController extends Controller
{
    public function dashboard()
    {
        return Inertia::render('User/Dashboard', $this->sharedData());
    }

    public function analytics()
    {
        return Inertia::render('User/Analytics', AnalyticsData::get());
    }

    public function forecasts()
    {
        return Inertia::render('User/ForecastResults', [
            'forecastTarget' => ForecastDataset::target(),
            'latestForecast' => ForecastDataset::currentForecasts()->with('user:id,name')->latest('predicted_at')->latest('id')->first(),
            'forecastHistory' => ForecastDataset::currentForecasts()->with('user:id,name')->latest('predicted_at')->latest('id')->take(20)->get(),
        ]);
    }

    public function decisionSupport()
    {
        return Inertia::render('User/DecisionSupportResults', [
            'latestDss' => DssResult::with(['user:id,name', 'forecastResult:id,year,month'])
                ->whereNotNull('forecast_result_id')
                ->latest('updated_at')
                ->first(),
            'history' => DssResult::with(['user:id,name', 'forecastResult:id,year,month'])
                ->whereNotNull('forecast_result_id')
                ->latest('updated_at')
                ->take(20)
                ->get(),
        ]);
    }

    public function reports()
    {
        return Inertia::render('User/Reports', [
            'reports' => Report::latest()->get(),
        ]);
    }

    private function sharedData(): array
    {
        $latestProcessed = ProcessedRecord::query()->orderByDesc('year')->orderByDesc('month')->first();
        $latestForecast = ForecastResult::latest('predicted_at')->latest('id')->first();
        $latestDss = DssResult::query()
            ->whereNotNull('forecast_result_id')
            ->latest('updated_at')
            ->first();
        $trend = ProcessedRecord::query()
            ->select('year', 'month', 'consumption_kwh', 'peak_demand_kw', 'solar_irradiance')
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->take(24)
            ->get()
            ->sortBy([
                ['year', 'asc'],
                ['month', 'asc'],
            ])
            ->values();

        return [
            'stats' => [
                'latest_average_consumption' => round((float) ($latestProcessed?->consumption_kwh ?? 0), 2),
                'latest_predicted_consumption' => round((float) ($latestForecast?->predicted_consumption_kwh ?? 0), 2),
                'latest_period' => $latestProcessed
                    ? $latestProcessed->year.'-'.str_pad((string) $latestProcessed->month, 2, '0', STR_PAD_LEFT)
                    : 'No data',
                'forecast_period' => $latestForecast
                    ? $latestForecast->year.'-'.str_pad((string) $latestForecast->month, 2, '0', STR_PAD_LEFT)
                    : 'No forecast',
                'forecast_change_percent' => round((float) ($latestForecast?->change_percent ?? 0), 2),
                'previous_consumption' => round((float) ($latestForecast?->previous_consumption_kwh ?? 0), 2),
                'peak_demand_kw' => round((float) ($latestProcessed?->peak_demand_kw ?? 0), 2),
                'solar_irradiance' => round((float) ($latestProcessed?->solar_irradiance ?? 0), 2),
                'demand_status' => $latestDss?->demand_status ?? 'No DSS result',
                'readiness_level' => $latestDss?->readiness_level ?? 'No assessment',
            ],
            'recommendations' => $latestDss?->recommendations ?? [],
            'priorityActions' => $latestDss?->priority_actions ?? [],
            'forecastHistory' => ForecastResult::latest('predicted_at')->latest('id')->take(10)->get(),
            'trend' => $trend,
        ];
    }
}
