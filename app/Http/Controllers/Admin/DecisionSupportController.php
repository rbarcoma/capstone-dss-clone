<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DssResult;
use App\Support\MlWorkflow;
use Illuminate\Http\Request;
use Inertia\Inertia;
use RuntimeException;

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
        try {
            // Uploads, manual generation and schedules share validation, caching
            // and locking, including after reverting to a previously used dataset.
            app(MlWorkflow::class)->predictAndGenerate(
                $request->user()?->id,
                'manual',
                false,
                refreshDssTimestamp: true,
            );
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Decision support recommendations generated.');
    }
}
