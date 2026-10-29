<?php

use App\Models\DssResult;
use App\Models\ForecastResult;
use App\Support\AuditLogger;
use App\Support\EnergyDss;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    expect(DB::connection()->getDatabaseName())->toBeIn(['testing', ':memory:']);
    $this->artisan('migrate:fresh')->assertSuccessful();
    $this->beforeApplicationDestroyed(function () {
        RefreshDatabaseState::$migrated = false;
    });
});

it('archives duplicate periods while preserving every original record and its history', function () {
    $migration = require database_path('migrations/2026_10_29_000002_enforce_one_current_dss_result_per_period.php');
    $migration->down();
    $originals = [];
    foreach (['a', 'b', 'c'] as $hash) {
        $forecast = ForecastResult::create([
            'dataset_hash' => str_repeat($hash, 64), 'year' => 2026, 'month' => 9,
            'predicted_consumption_kwh' => 1000,
        ]);
        $values = [
            'forecast_result_id' => $forecast->id,
            'demand_status' => 'Moderate Demand', 'readiness_level' => 'High Readiness',
            'recommendations' => json_encode(['Original '.$hash]),
            'priority_actions' => json_encode(['Action '.$hash]),
            'basis' => json_encode(['predicted_consumption_kwh' => 1000]),
            'created_at' => '2026-09-01 00:00:00', 'updated_at' => '2026-09-01 01:00:00',
        ];
        $id = DB::table('dss_results')->insertGetId($values);
        $originals[$id] = (array) DB::table('dss_results')->find($id);
    }
    $keeperId = max(array_keys($originals));
    $orphanId = DB::table('dss_results')->insertGetId([...$values, 'forecast_result_id' => null]);
    DB::table('reports')->insert([
        'title' => 'Original report', 'type' => 'full',
        'summary' => json_encode(['latest_dss' => $originals[array_key_first($originals)]]),
    ]);
    AuditLogger::log('Decision Support', 'Generate DSS Result', 'Original history');
    $audits = DB::table('audit_trails')->get()->toJson();
    $reports = DB::table('reports')->get()->toJson();
    $migration->up();

    expect(DssResult::sole()->id)->toBe($keeperId)
        ->and(DssResult::withoutGlobalScopes()->count())->toBe(4)
        ->and(DssResult::sole()->forecast_year)->toBe(2026)
        ->and(DssResult::sole()->forecast_month)->toBe(9)
        ->and(ForecastResult::count())->toBe(3)
        ->and(DB::table('audit_trails')->get()->toJson())->toBe($audits)
        ->and(DB::table('reports')->get()->toJson())->toBe($reports)
        ->and(DB::table('dss_results')->find($orphanId)->archived_at)->not->toBeNull();
    foreach ($originals as $id => $original) {
        $stored = (array) DB::table('dss_results')->find($id);
        expect(array_intersect_key($stored, $original))->toEqual($original);
        if ($id !== $keeperId) {
            expect($stored['superseded_by_id'])->toBe($keeperId)
                ->and($stored['archived_at'])->not->toBeNull();
        }
    }
    $this->expectException(UniqueConstraintViolationException::class);
    DB::table('dss_results')->where('id', array_key_first($originals))->update(['archived_at' => null]);
});

it('retains result revisions across rollback and migration reapplication', function () {
    $forecast = ForecastResult::create(['year' => 2026, 'month' => 9, 'predicted_consumption_kwh' => 1000]);
    $dss = EnergyDss::generateForForecast($forecast);
    $forecast->update(['predicted_consumption_kwh' => 2000]);
    EnergyDss::generateForForecast($forecast);
    $revisions = DB::table('dss_result_revisions')->get()->toJson();
    $migration = require database_path('migrations/2026_10_29_000002_enforce_one_current_dss_result_per_period.php');
    $migration->down();
    $migration->up();
    expect(DB::table('dss_result_revisions')->get()->toJson())->toBe($revisions)
        ->and(DssResult::sole()->id)->toBe($dss->id);
});
