<?php

use App\Models\ClimateRecord;
use App\Models\ConsumptionRecord;
use App\Models\Dataset;
use App\Models\ForecastResult;
use App\Models\ProcessedRecord;
use App\Models\Report;
use App\Models\User;
use App\Support\AnalyticsData;
use App\Support\ForecastDataset;
use App\Support\MlWorkflow;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Process\Factory;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

function forecastActual(int $year, int $month, bool $complete = true): void
{
    $dataset = Dataset::firstOrCreate(['original_name' => 'uploaded.csv'], [
        'type' => 'combined', 'path' => 'datasets/uploaded.csv', 'status' => 'valid',
    ]);
    if (! Storage::exists($dataset->path)) {
        Storage::put($dataset->path, 'year,month,consumption_kwh,peak_demand_kw,temperature,humidity,rainfall,solar_irradiance');
    }
    Storage::append($dataset->path, "$year,$month,1000,100,25,60,10,5");
    ClimateRecord::create([
        'dataset_id' => $dataset->id, 'year' => $year, 'month' => $month,
        'temperature' => 25, 'humidity' => 60, 'rainfall' => 10, 'solar_irradiance' => 5,
    ]);
    ConsumptionRecord::create([
        'dataset_id' => $dataset->id,
        'year' => $year, 'month' => $month, 'consumption_kwh' => 1000,
        'peak_demand_kw' => 100, 'is_complete' => $complete,
    ]);
    ProcessedRecord::create([
        'year' => $year, 'month' => $month, 'consumption_kwh' => 1000,
        'peak_demand_kw' => 100, 'temperature' => 25, 'humidity' => 60,
        'rainfall' => 10, 'solar_irradiance' => 5, 'lag_1' => 1000,
        'lag_2' => 1000, 'trend' => $month, 'month_sin' => 0, 'month_cos' => 1,
    ]);
}

beforeEach(function () {
    Storage::fake('local');
    Process::fake(function ($process) {
        if ($process->command[1] === 'train_model.py') {
            file_put_contents($process->command[3], 'model trained on uploaded data');
            file_put_contents($process->command[4], '{}');

            return Process::result(output: 'Trained');
        }
        if ($process->command[1] === 'predict_history.py') {
            $lines = array_slice(file($process->command[2], FILE_IGNORE_NEW_LINES), 1);

            return Process::result(output: json_encode(array_map(function ($line) {
                [$year, $month] = str_getcsv($line, escape: '');

                return ['period' => sprintf('%04d-%02d', $year, $month), 'predicted' => 980];
            }, $lines)));
        }

        return Process::result(output: json_encode([
            'year' => 2026, 'month' => 10, 'predicted_consumption_kwh' => 1100,
            'previous_consumption_kwh' => 1000, 'change_percent' => 10,
        ]));
    });
    foreach (range(4, 9) as $month) {
        forecastActual(2026, $month);
    }
});

it('targets October independently of the calendar date', function (string $today) {
    $this->travelTo(new DateTimeImmutable($today));
    $result = app(MlWorkflow::class)->predictAndGenerate();

    expect($result['forecast']->year)->toBe(2026)
        ->and($result['forecast']->month)->toBe(10)
        ->and($result['report']->summary['prediction_explanation']['forecast_period'])->toBe('2026-10')
        ->and($result['report']->summary['latest_forecast']['id'])->toBe($result['forecast']->id);
    Process::assertRan(fn ($process) => $process->command[1] === 'predict_next_month.py');
    Process::assertRanTimes(fn () => true, 2);
})->with(['2026-08-01', '2026-11-20', '2028-02-15']);

it('does not skip a missing month even when later actuals exist', function () {
    forecastActual(2026, 11);
    $snapshot = ForecastDataset::snapshot();
    expect($snapshot['month'])->toBe(10)->and($snapshot['records'])->toHaveCount(6);
    $result = app(MlWorkflow::class)->predictAndGenerate();
    expect(Storage::get('ml/processed_dataset.csv'))->not->toContain('2026,11,');
    expect($result['dss']->basis['average_consumption_kwh'])->toEqual(1000);
    expect($result['report']->summary['analytics']['latest_period'])->toBe('2026-09');
});

it('waits for complete October actuals before targeting November', function () {
    forecastActual(2026, 10, false);
    expect(ForecastDataset::target())->toBe(['year' => 2026, 'month' => 10]);
    ConsumptionRecord::where('month', 10)->update(['is_complete' => true]);
    expect(ForecastDataset::target())->toBe(['year' => 2026, 'month' => 11]);
});

it('rejects invalid values and stale preprocessing as a forecast basis', function () {
    forecastActual(2026, 10);
    ConsumptionRecord::where('month', 10)->update(['consumption_kwh' => -1]);
    expect(ForecastDataset::target()['month'])->toBe(10);
    ConsumptionRecord::where('month', 10)->update(['consumption_kwh' => 1200]);
    expect(ForecastDataset::target())->toBeNull();
    expect(fn () => app(MlWorkflow::class)->predictAndGenerate())->toThrow(RuntimeException::class, 'Run preprocessing');
});

it('rolls December over to January of the following year', function () {
    foreach (range(10, 12) as $month) {
        forecastActual(2026, $month);
    }
    expect(ForecastDataset::target())->toBe(['year' => 2027, 'month' => 1]);
});

it('reuses the same forecast and report for manual upload and scheduled requests', function () {
    $workflow = app(MlWorkflow::class);
    $first = $workflow->predictAndGenerate();
    $second = $workflow->runScheduledMonthlyPrediction();
    $third = $workflow->runAfterDatasetUpload();
    expect($second['forecast']->id)->toBe($first['forecast']->id)
        ->and($third['forecast']->id)->toBe($first['forecast']->id)
        ->and($second['report']->id)->toBe($first['report']->id)
        ->and(ForecastResult::count())->toBe(1)
        ->and(Report::count())->toBe(1);
    Process::assertRanTimes(fn () => true, 2);
});

it('preserves forecast history when the dataset changes for the same target', function () {
    $first = app(MlWorkflow::class)->predictAndGenerate();
    ConsumptionRecord::where('month', 9)->update(['consumption_kwh' => 1200]);
    ProcessedRecord::where('month', 9)->update(['consumption_kwh' => 1200]);
    $second = app(MlWorkflow::class)->predictAndGenerate();
    expect($second['forecast']->id)->not->toBe($first['forecast']->id)
        ->and($second['forecast']->dataset_hash)->not->toBe($first['forecast']->dataset_hash)
        ->and(ForecastResult::count())->toBe(2)
        ->and($first['report']->fresh()->summary['latest_forecast']['id'])->toBe($first['forecast']->id);
    $this->expectException(UniqueConstraintViolationException::class);
    ForecastResult::create($second['forecast']->only(['dataset_hash', 'year', 'month', 'predicted_consumption_kwh']));
});

it('refuses a Python result beyond the next dataset month', function () {
    Process::fake(fn () => Process::result(output: '{"year":2026,"month":11,"predicted_consumption_kwh":1100}'));
    expect(fn () => app(MlWorkflow::class)->predictAndGenerate())->toThrow(RuntimeException::class);
    expect(ForecastResult::count())->toBe(0);
});

it('checks legacy daily uploads instead of trusting an incomplete aggregate', function () {
    forecastActual(2026, 10);
    $dataset = Dataset::create(['type' => 'combined', 'original_name' => 'daily.csv', 'path' => 'daily.csv', 'status' => 'valid']);
    Storage::put('daily.csv', "year,month,day,consumption_kwh,peak_demand_kw,temperature,humidity,rainfall,solar_irradiance\n2026,10,1,1000,100,25,60,10,5");
    ConsumptionRecord::where('month', 10)->update(['dataset_id' => $dataset->id, 'is_complete' => null]);
    expect(ForecastDataset::target()['month'])->toBe(10);
});

it('exposes the dataset target in the forecasting page and controller', function () {
    $this->travelTo(new DateTimeImmutable('2027-06-01'));
    $admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($admin)->get(route('admin.forecasting'))->assertInertia(fn (Assert $page) => $page
        ->component('Admin/Forecasting')->where('forecastTarget', ['year' => 2026, 'month' => 10]));
    $this->post(route('admin.forecasting.predict'))->assertSessionHas('success');
    expect(ForecastResult::first()->month)->toBe(10);
    Process::assertRanTimes(fn () => true, 2);
});

it('marks a partial daily upload incomplete before automatic forecasting', function () {
    $lines = ['year,month,day,consumption_kwh,peak_demand_kw,temperature,humidity,rainfall,solar_irradiance'];
    foreach (range(4, 10) as $month) {
        $days = $month === 10 ? 15 : cal_days_in_month(CAL_GREGORIAN, $month, 2026);
        foreach (range(1, $days) as $day) {
            $lines[] = "2026,$month,$day,1000,100,25,60,10,5";
        }
    }
    $this->actingAs(User::factory()->create(['role' => 'admin']))
        ->post(route('admin.data-management.store'), [
            'dataset' => UploadedFile::fake()->createWithContent('daily.csv', implode("\n", $lines)),
        ])->assertSessionHas('success');
    expect(ConsumptionRecord::where('month', 9)->first()->is_complete)->toBeTrue()
        ->and(ConsumptionRecord::where('month', 10)->first()->is_complete)->toBeFalse()
        ->and(ForecastResult::first()->month)->toBe(10);
    Process::assertRanTimes(fn () => true, 2);
});

it('preserves legacy forecasts without a dataset version', function () {
    $legacy = ForecastResult::create(['year' => 2026, 'month' => 10, 'predicted_consumption_kwh' => 900]);
    $result = app(MlWorkflow::class)->predictAndGenerate();
    expect($result['forecast']->id)->not->toBe($legacy->id)
        ->and($legacy->fresh()->predicted_consumption_kwh)->toEqual(900)
        ->and(ForecastResult::count())->toBe(2);
});

it('hides orphaned actuals forecasts and cached model predictions when no upload exists', function (string $role) {
    Dataset::query()->delete();
    ForecastResult::create(['year' => 2026, 'month' => 10, 'predicted_consumption_kwh' => 9999]);
    Storage::put('ml/processed_dataset.csv', 'stale export');
    Storage::put('ml/model_state.json', json_encode(['model_path' => 'ml/stale.pkl']));
    Storage::put('ml/stale.pkl', 'stale model');
    $this->actingAs(User::factory()->create(['role' => $role]));

    $this->get(route("$role.analytics"))->assertInertia(fn (Assert $page) => $page
        ->has('monthlyTrend', 0)->has('actualPredictedTrend', 0)
        ->has('yearlyComparison', 0)->has('seasonalPattern', 0)
        ->where('summary.average_consumption', null)->where('summary.highest_consumption', null));
    $this->get(route($role === 'admin' ? 'admin.forecasting' : 'user.forecast-results'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('forecastTarget', null)->where('latestForecast', null)->has('forecastHistory', 0));
    expect(fn () => app(MlWorkflow::class)->predictAndGenerate())->toThrow(RuntimeException::class);
    expect(fn () => app(MlWorkflow::class)->trainIfDatasetChanged())->toThrow(RuntimeException::class);
    Process::assertNothingRan();
})->with(['admin', 'user']);

it('restores historical estimates for existing uploads without generating future forecasts', function (string $role) {
    ForecastResult::create(['year' => 2026, 'month' => 10, 'predicted_consumption_kwh' => 9999]);
    $this->actingAs(User::factory()->create(['role' => $role]))
        ->get(route("$role.analytics"))->assertInertia(fn (Assert $page) => $page
        ->has('monthlyTrend', 6)->has('actualPredictedTrend', 6)
        ->where('actualPredictedTrend.0.actual', 1000)
        ->where('actualPredictedTrend.0.predicted', 980)
        ->where('actualPredictedTrend.0.accuracy', 98)
        ->where('actualPredictedTrend.5.period', '2026-09')
        ->where('actualPredictedTrend.5.predicted', 980));
    // Reloads reuse estimates tied to the same validated upload.
    $this->get(route("$role.analytics"))->assertOk();
    expect(ForecastResult::count())->toBe(1)->and(Report::count())->toBe(0);
    Process::assertRanTimes(fn ($process) => $process->command[1] === 'train_model.py', 1);
    Process::assertRanTimes(fn ($process) => $process->command[1] === 'predict_history.py', 1);
    Process::assertNotRan(fn ($process) => $process->command[1] === 'predict_next_month.py');
})->with(['admin', 'user']);

it('shows the same generated kWh result in both analytics and forecasting for both roles', function () {
    $forecast = app(MlWorkflow::class)->predictAndGenerate()['forecast'];
    foreach (['admin', 'user'] as $role) {
        $this->actingAs(User::factory()->create(['role' => $role]));
        $this->get(route("$role.analytics"))->assertInertia(fn (Assert $page) => $page
            ->has('actualPredictedTrend', 7)
            ->where('actualPredictedTrend.0.actual', 1000)->where('actualPredictedTrend.0.predicted', 980)
            ->where('actualPredictedTrend.6.period', '2026-10')
            ->where('actualPredictedTrend.6.predicted', 1100)->where('actualPredictedTrend.6.actual', null));
        $this->get(route($role === 'admin' ? 'admin.forecasting' : 'user.forecast-results'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('latestForecast.id', $forecast->id)->has('forecastHistory', 1));
    }
    Process::assertRanTimes(fn ($process) => $process->command[1] === 'train_model.py', 1);
    Process::assertRanTimes(fn ($process) => $process->command[1] === 'predict_next_month.py', 1);
});

it('hides previous results immediately when uploaded inputs change', function () {
    app(MlWorkflow::class)->predictAndGenerate();
    $original = ForecastDataset::snapshot()['dataset_hash'];
    AnalyticsData::get();
    ConsumptionRecord::where('month', 9)->update(['consumption_kwh' => 1200]);
    ProcessedRecord::where('month', 9)->update(['consumption_kwh' => 1200]);
    expect(ForecastDataset::currentForecasts()->count())->toBe(0);
    $analytics = AnalyticsData::get();
    expect($analytics['actualPredictedTrend'])->toHaveCount(6)
        ->and($analytics['actualPredictedTrend']->last()['period'])->toBe('2026-09')
        ->and($analytics['actualPredictedTrend']->last()['actual'])->toBe(1200.0)
        ->and(ForecastDataset::snapshot()['dataset_hash'])->not->toBe($original);
    app(MlWorkflow::class)->predictAndGenerate();
    expect(ForecastDataset::currentForecasts()->count())->toBe(1);
    Process::assertRanTimes(fn ($process) => $process->command[1] === 'train_model.py', 2);
    Process::assertRanTimes(fn ($process) => $process->command[1] === 'predict_history.py', 2);
});

it('refuses missing invalid or insufficient uploaded sources even with saved results', function (string $state) {
    app(MlWorkflow::class)->predictAndGenerate();
    $dataset = Dataset::first();
    match ($state) {
        'missing' => Storage::delete($dataset->path),
        'invalid' => $dataset->update(['status' => 'invalid']),
        'insufficient' => ConsumptionRecord::where('month', 9)->delete(),
    };
    expect(ForecastDataset::target())->toBeNull()
        ->and(ForecastDataset::currentForecasts()->count())->toBe(0);
    expect(fn () => app(MlWorkflow::class)->predictAndGenerate())->toThrow(RuntimeException::class);
})->with(['missing', 'invalid', 'insufficient']);

it('clears analytics and forecasting after deleting the last uploaded dataset', function () {
    app(MlWorkflow::class)->predictAndGenerate();
    $admin = User::factory()->create(['role' => 'admin', 'password' => 'password']);
    $this->actingAs($admin)->delete(route('admin.data-management.destroy', Dataset::first()), [
        'password' => 'password',
    ])->assertSessionHas('success');
    expect(ConsumptionRecord::count())->toBe(0)->and(ProcessedRecord::count())->toBe(0)
        ->and(ForecastDataset::currentForecasts()->count())->toBe(0);
    $this->get(route('admin.analytics'))->assertInertia(fn (Assert $page) => $page
        ->has('monthlyTrend', 0)->has('actualPredictedTrend', 0));
});

it('rejects empty malformed and nonnumeric uploads without replacing good data', function (string $contents) {
    $dataset = Dataset::first();
    $original = Storage::get($dataset->path);
    $this->actingAs(User::factory()->create(['role' => 'admin']))
        ->post(route('admin.data-management.store'), [
            'dataset' => UploadedFile::fake()->createWithContent('uploaded.csv', $contents),
            'replace_existing' => true,
        ])->assertSessionHasErrors('dataset');
    expect(Storage::get($dataset->fresh()->path))->toBe($original)
        ->and(ConsumptionRecord::count())->toBe(6);
    Process::assertNothingRan();
})->with([
    'headers only' => "year,month,consumption_kwh,peak_demand_kw,temperature,humidity,rainfall,solar_irradiance\n",
    'nonnumeric' => "year,month,consumption_kwh,peak_demand_kw,temperature,humidity,rainfall,solar_irradiance\n2026,10,no,100,25,60,10,5",
    'malformed' => "year,month,consumption_kwh,peak_demand_kw,temperature,humidity,rainfall,solar_irradiance\n2026,10,1000,100,25,60,10,5,extra",
    'negative' => "year,month,consumption_kwh,peak_demand_kw,temperature,humidity,rainfall,solar_irradiance\n2026,10,-1,100,25,60,10,5",
]);

it('does not fall back to a bundled model when training fails', function () {
    Process::fake(fn () => Process::result(errorOutput: 'Training failed', exitCode: 1));
    expect(fn () => app(MlWorkflow::class)->predictAndGenerate())->toThrow(RuntimeException::class, 'Training failed');
    expect(ForecastResult::count())->toBe(0);
    Process::assertNotRan(fn ($process) => $process->command[1] === 'predict_next_month.py');
});

it('trains and predicts through the real Python pipeline using uploaded consumption', function () {
    Process::swap(new Factory);
    config(['ml.python' => 'python3']);
    $first = app(MlWorkflow::class)->predictAndGenerate(null, 'manual', false)['forecast'];
    expect((float) $first->predicted_consumption_kwh)->toBe(1000.0);
    expect(AnalyticsData::get()['actualPredictedTrend']->first()['predicted'])->toBe(1000.0);
    ConsumptionRecord::query()->update(['consumption_kwh' => 2000]);
    ProcessedRecord::query()->update(['consumption_kwh' => 2000, 'lag_1' => 2000, 'lag_2' => 2000]);
    $dataset = Dataset::first();
    Storage::put($dataset->path, str_replace(',1000,', ',2000,', Storage::get($dataset->path)));
    $second = app(MlWorkflow::class)->predictAndGenerate(null, 'manual', false)['forecast'];
    expect((float) $second->predicted_consumption_kwh)->toBe(2000.0)
        ->and($second->dataset_hash)->not->toBe($first->dataset_hash);
    expect(AnalyticsData::get()['actualPredictedTrend']->first()['predicted'])->toBe(2000.0);
});

it('excludes stale climate preprocessing from actuals and forecast generation', function () {
    ClimateRecord::where('month', 9)->update(['temperature' => 30]);
    expect(ForecastDataset::actualRecords())->toHaveCount(5)
        ->and(ForecastDataset::target())->toBeNull();
    expect(fn () => app(MlWorkflow::class)->predictAndGenerate())->toThrow(RuntimeException::class, 'Run preprocessing');
    Process::assertNothingRan();
});

it('preserves genuine zero consumption as actual data', function () {
    ConsumptionRecord::query()->update(['consumption_kwh' => 0]);
    ProcessedRecord::query()->update(['consumption_kwh' => 0, 'lag_1' => 0, 'lag_2' => 0]);
    $dataset = Dataset::first();
    Storage::put($dataset->path, str_replace(',1000,', ',0,', Storage::get($dataset->path)));
    $analytics = AnalyticsData::get();
    expect($analytics['summary']['average_consumption'])->toBe(0.0)
        ->and($analytics['actualPredictedTrend']->first()['actual'])->toBe(0.0)
        ->and($analytics['actualPredictedTrend']->first()['predicted'])->toBe(980.0);
});

it('does not expose cached historical estimates after the upload becomes unavailable', function (string $state) {
    expect(AnalyticsData::get()['actualPredictedTrend']->first()['predicted'])->toBe(980.0);
    $dataset = Dataset::first();
    match ($state) {
        'missing' => Storage::delete($dataset->path),
        'invalid' => $dataset->update(['status' => 'invalid']),
        'insufficient' => ConsumptionRecord::where('month', 9)->delete(),
        'stale' => ClimateRecord::where('month', 9)->update(['temperature' => 30]),
    };
    expect(AnalyticsData::get()['actualPredictedTrend']->pluck('predicted')->filter())->toBeEmpty();
    Process::assertRanTimes(fn () => true, 2);
})->with(['missing', 'invalid', 'insufficient', 'stale']);

it('keeps actuals visible without estimates when historical training or prediction fails', function (string $failure) {
    Process::fake(function ($process) use ($failure) {
        if ($failure !== 'training' && $process->command[1] === 'train_model.py') {
            file_put_contents($process->command[3], 'model');
            file_put_contents($process->command[4], '{}');

            return Process::result(output: 'Trained');
        }

        return match ($failure) {
            'periods' => Process::result(output: '[{"period":"2026-01","predicted":900}]'),
            'value' => Process::result(output: json_encode(array_map(fn ($month) => [
                'period' => sprintf('2026-%02d', $month), 'predicted' => -1,
            ], range(4, 9)))),
            default => Process::result(errorOutput: 'Python unavailable', exitCode: 1),
        };
    });
    $analytics = AnalyticsData::get();
    expect($analytics['actualPredictedTrend'])->toHaveCount(6)
        ->and($analytics['actualPredictedTrend']->pluck('predicted')->filter())->toBeEmpty()
        ->and($analytics['summary']['average_consumption'])->toBe(1000.0)
        ->and(ForecastResult::count())->toBe(0);
})->with(['training', 'prediction', 'periods', 'value']);
