<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DssResult;
use App\Models\ForecastResult;
use App\Support\AuditLogger;
use App\Models\Report;
use App\Support\ReportGenerator;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ReportController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/Reports', [
            'reports' => Report::with('user:id,name')->latest()->get(),
            'latestForecast' => ForecastResult::latest('predicted_at')->latest('id')->first(),
            'latestDss' => DssResult::latest('updated_at')->first(),
        ]);
    }

    public function store(Request $request)
    {
        $report = ReportGenerator::create($request->user()->id, 'manual');

        AuditLogger::log(
            'Reports',
            'Generate Report',
            'Generated report: ' . $report->title . '.'
        );

        return back()->with('success', "Report #{$report->id} generated.");
    }

    public function download(Report $report)
    {
        $report->load('user:id,name');
        $html = view('reports.energy', ['report' => $report])->render();
        
        AuditLogger::log(
            'Reports',
            'Open / Print Report',
            'Opened or printed report: ' . $report->title . '.'
        );

        return response($html, 200, [
            'Content-Type' => 'text/html',
            'Content-Disposition' => 'inline; filename="energy-transition-report-'.$report->id.'.html"',
        ]);
    }
}
