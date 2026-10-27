<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DssResult;
use App\Models\ForecastResult;
use App\Support\AuditLogger;
use App\Support\EnergyDss;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DecisionSupportController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/DecisionSupport', [
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

    public function generate(Request $request)
    {
        $forecast = ForecastResult::latest('predicted_at')->latest('id')->first();
        if (! $forecast) {
            return back()->with('error', 'Run a forecast before generating DSS recommendations.');
        }

        $dss = EnergyDss::generateForForecast($forecast, $request->user()?->id, now());

        AuditLogger::log(
            'Decision Support',
            'Generate DSS Result',
            'Generated DSS result: ' . $dss->demand_status . ' and ' . $dss->readiness_level . '.'
        );

        return back()->with('success', 'Decision support recommendations generated.');
    }
}
