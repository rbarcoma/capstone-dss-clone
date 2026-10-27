<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\ForecastDataset;
use App\Support\MlWorkflow;
use Illuminate\Http\Request;
use Inertia\Inertia;
use RuntimeException;

class ForecastingController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/Forecasting', [
            'forecastTarget' => ForecastDataset::target(),
            'latestForecast' => ForecastDataset::currentForecasts()->with('user:id,name')->latest('predicted_at')->latest('id')->first(),
            'forecastHistory' => ForecastDataset::currentForecasts()->with('user:id,name')->latest('predicted_at')->latest('id')->take(20)->get(),
        ]);
    }

    public function train()
    {
        try {
            $result = app(MlWorkflow::class)->trainIfDatasetChanged(auth()->id(), true);
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Model trained successfully. '.($result['output'] ?? ''));
    }

    public function predict(Request $request)
    {
        try {
            $result = app(MlWorkflow::class)->predictAndGenerate($request->user()?->id, 'manual');
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        $forecast = $result['forecast'];

        return back()->with('success', sprintf('Dataset-based forecast for %04d-%02d is saved with DSS recommendations and report.', $forecast->year, $forecast->month));
    }
}
