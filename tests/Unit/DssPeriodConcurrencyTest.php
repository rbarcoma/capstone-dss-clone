<?php

use App\Models\DssResult;
use App\Models\ForecastResult;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\TestCase;

uses(TestCase::class);

it('serializes simultaneous monthly DSS creation and updates on MySQL', function () {
    if (DB::getDriverName() !== 'mysql') {
        $this->markTestSkipped('Requires MySQL locks and committed fixtures shared with independent workers.');
    }
    expect(DB::connection()->getDatabaseName())->toBe('testing');
    $this->artisan('migrate:fresh')->assertSuccessful();
    $this->beforeApplicationDestroyed(function () {
        RefreshDatabaseState::$migrated = false;
    });
    $forecast = ForecastResult::create([
        'dataset_hash' => str_repeat('a', 64), 'year' => 2026, 'month' => 10,
        'predicted_consumption_kwh' => 1000,
    ]);
    $runWorkers = function (ForecastResult $forecast) {
        $barrier = tempnam(sys_get_temp_dir(), 'dss-barrier-');
        unlink($barrier);
        $workers = [];
        try {
            foreach (range(1, 6) as $i) {
                $worker = new Process([PHP_BINARY, base_path('tests/Support/generate-dss-worker.php'), (string) $forecast->id, $barrier], base_path(), [
                    'APP_ENV' => 'testing', 'DB_CONNECTION' => 'mysql', 'DB_DATABASE' => 'testing', 'DB_URL' => '',
                    'CACHE_STORE' => 'array',
                ]);
                $worker->start();
                $workers[] = $worker;
            }
            touch($barrier);
            $ids = [];
            foreach ($workers as $worker) {
                $worker->wait();
                expect($worker->isSuccessful())->toBeTrue($worker->getErrorOutput().$worker->getOutput());
                $ids[] = (int) $worker->getOutput();
            }

            return array_unique($ids);
        } finally {
            foreach ($workers as $worker) {
                $worker->stop();
            }
            @unlink($barrier);
        }
    };
    expect($runWorkers($forecast))->toHaveCount(1)
        ->and(DssResult::count())->toBe(1)
        ->and(DB::table('dss_result_revisions')->count())->toBe(0);
    $id = DssResult::sole()->id;
    $replacement = ForecastResult::create([
        'dataset_hash' => str_repeat('b', 64), 'year' => 2026, 'month' => 10,
        'predicted_consumption_kwh' => 2000,
    ]);
    expect($runWorkers($replacement))->toBe([$id])
        ->and(DssResult::count())->toBe(1)
        ->and(DssResult::sole()->basis['predicted_consumption_kwh'])->toEqual(2000)
        ->and(DssResult::sole()->forecast_result_id)->toBe($replacement->id)
        ->and(DB::table('dss_result_revisions')->count())->toBe(1);
});
