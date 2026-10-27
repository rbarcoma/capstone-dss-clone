<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Admin\ForecastingController;
use Illuminate\Http\Request;

// Retain the legacy entry points while sharing dataset targeting and deduplication.
class ForecastController extends ForecastingController
{
    public function runForecast(Request $request)
    {
        abort_unless(auth()->check() && auth()->user()->role === 'admin', 403);

        return $this->predict($request);
    }
}
